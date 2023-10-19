<?php
/**
 * class ElasticConfigureTask|Firesphere\ElasticSearch\Tasks\ElasticConfigureTask Configure custom mappings in Elastic
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Tasks;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Exception;
use Firesphere\ElasticSearch\Helpers\Statics;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Helpers\FieldResolver;
use Firesphere\SearchBackend\Indexes\CoreIndex;
use Firesphere\SearchBackend\Traits\LoggerTrait;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Class ElasticConfigureTask
 *
 * Used to create field-specific mappings in Elastic. Not explicitly needed for a basic search
 * functionality, but does add filter/sort/aggregate features without the performance drawback
 */
class ElasticConfigureTask extends BuildTask
{
    use LoggerTrait;

    /**
     * @var string URLSegment
     */
    private static $segment = 'ElasticConfigureTask';
    /**
     * DBHTML and DBText etc. should never be made sortable
     * It doesn't make sense for large text objects
     * @var string[]
     */
    private static $unSsortables = [
        'HTML',
        'Text'
    ];
    /**
     * @var bool[]
     */
    public $result;
    /**
     * @var string Title
     */
    protected $title = 'Configure Elastic cores';
    /**
     * @var string Description
     */
    protected $description = 'Create or reload a Elastic Core by adding or reloading a configuration.';
    /**
     * @var ElasticCoreService $service
     */
    protected $service;

    /**
     * @throws NotFoundExceptionInterface
     */
    public function __construct()
    {
        parent::__construct();
        $this->service = Injector::inst()->get(ElasticCoreService::class);
    }

    /**
     * Run the config
     *
     * @param HTTPRequest $request
     * @return void
     * @throws NotFoundExceptionInterface
     */
    public function run($request)
    {
        $this->extend('onBeforeElasticConfigureTask', $request);

        $indexes = $this->service->getValidIndexes();

        $result = [];

        foreach ($indexes as $index) {
            try {
                /** @var ElasticIndex $instance */
                $instance = Injector::inst()->get($index, false);
                // If delete in advance, do so
                $instance->deleteIndex($request);
                $configResult = $this->configureIndex($instance);
                $result[] = $configResult->asBool();
            } catch (Exception $error) {
                // @codeCoverageIgnoreStart
                $this->getLogger()->error(sprintf('Core loading failed for %s', $index));
                $this->getLogger()->error($error->getMessage()); // in browser mode, it might not always show
                // Continue to the next index
                continue;
                // @codeCoverageIgnoreEnd
            }
            $this->extend('onAfterConfigureIndex', $index);
        }

        $this->extend('onAfterElasticConfigureTask');

        if ($request->getVar('istest')) {
            $this->result = $result;
        }
    }

    /**
     * Update/create a single index.
     * @param ElasticIndex $index
     * @return Elasticsearch
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NotFoundExceptionInterface
     * @throws ServerResponseException
     */
    public function configureIndex(CoreIndex $index): Elasticsearch
    {
        $indexName = $index->getIndexName();

        $instanceConfig = $this->createConfigForIndex($index);

        $mappings = $this->convertForJSON($instanceConfig);

        $body = ['index' => $indexName];
        $client = $this->service->getClient();

        $method = $this->getMethod($index);
        $msg = "%s index %s";
        $msgType = 'Updating';
        if ($method === 'create') {
            $mappings = ['mappings' => $mappings];
            $msgType = 'Creating';
        }
        $body['body'] = $mappings;
        $msg = sprintf($msg, $msgType, $indexName);
        DB::alteration_message($msg);
        $this->getLogger()->info($msg);

        return $client->indices()->$method($body);
    }

    /**
     * @param ElasticIndex $instance
     * @return array
     * @throws NotFoundExceptionInterface
     */
    protected function createConfigForIndex(ElasticIndex $instance)
    {
        /** @var FieldResolver $resolver */
        $resolver = Injector::inst()->get(FieldResolver::class);
        $resolver->setIndex($instance);
        $result = [];

        foreach ($instance->getFulltextFields() as $field) {
            $field = $resolver->resolveField($field);
            $result = array_merge($result, $field);
        }

        return $result;
    }

    /**
     * Take the config from the resolver and build an array that's
     * ready to be converted to JSON for Elastic.
     *
     * @param $config
     * @return array[]
     */
    protected function convertForJSON($config)
    {
        $base = [];
        $typeMap = Statics::getTypeMap();
        foreach ($config as $key => &$conf) {
            $shortClass = ClassInfo::shortName($conf['origin']);
            $dotField = str_replace('_', '.', $conf['fullfield']);
            $conf['name'] = sprintf('%s.%s', $shortClass, $dotField);
            $base[$conf['name']] = [
                'type' => $typeMap[$conf['type'] ?? '*']
            ];
            $shouldHold = true;
            foreach (self::$unSsortables as $unSortable) {
                $shouldHold = !str_contains($conf['type'], $unSortable) && $shouldHold;
            }
            if ($shouldHold && $typeMap[$conf['type']] === 'text') {
                $base[$conf['name']]['fielddata'] = true;
            }
        }

        return ['properties' => $base];
    }

    /**
     * Get the method to use. Create or Update
     *
     * WARNING: Update often fails because Elastic does not allow changing
     * of mappings on-the-fly, it will commonly require a delete-and-recreate!
     *
     * @param ElasticIndex $index
     * @return string
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    protected function getMethod(ElasticIndex $index): string
    {
        $check = $index->indexExists();

        if ($check) {
            return 'putMapping';
        }

        return 'create';
    }

    /**
     * @return ElasticCoreService|mixed|object|Injector
     */
    public function getService(): mixed
    {
        return $this->service;
    }
}

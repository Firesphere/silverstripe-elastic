<?php

namespace Firesphere\ElasticSearch\Tasks;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Firesphere\ElasticSearch\Helpers\Statics;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Helpers\FieldResolver;
use Firesphere\SearchBackend\Traits\LoggerTrait;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

class ElasticConfigureTask extends BuildTask
{
    use LoggerTrait;

    /**
     * @var string URLSegment
     */
    private static $segment = 'ElasticConfigureTask';
    /**
     * @var string Title
     */
    protected $title = 'Configure Elastic cores';
    /**
     * @var string Description
     */
    protected $description = 'Create or reload a Elastic Core by adding or reloading a configuration.';


    public function run($request)
    {
        $this->extend('onBeforeElasticConfigureTask', $request);

        /** @var ElasticCoreService $service */
        $service = Injector::inst()->get(ElasticCoreService::class);

        $indexes = $service->getValidIndexes();


        foreach ($indexes as $index) {
            try {
                if ($request->getVar('clear')) {
                    $this->getLogger()->info(sprintf('Clearing index %s', $index));
                    $service->getClient()->indices()->delete(['index' => $index]);
                }

                $this->configureIndex($index, $service);
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
    }

    /**
     * Update/create a store
     * @param string $index
     * @param ElasticCoreService $service
     * @return void
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws NotFoundExceptionInterface
     */
    protected function configureIndex($index, ElasticCoreService $service)
    {
        /** @var ElasticIndex $instance */
        $instance = Injector::inst()->get($index, false);

        $indexName = $instance->getIndexName();


        $instanceConfig = $this->createConfigForIndex($instance);

        $body = $this->configToJSON($instanceConfig);
        $body['index'] = $indexName;
        $client = $service->getClient();

        $method = $this->getMethod($instance, $service);
        if ($method === 'update') {
            $client->indices()->putMapping($body);
        } else {
            $client->indices()->create($body);
        }
    }

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

    protected function configToJSON($config)
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
        }

        $mappings = ['properties' => $base];

        return ['body' => ['mappings' => $mappings]];
    }

    protected function getMethod(ElasticIndex $index, ElasticCoreService $service)
    {
        $check = $service->getClient()
            ->indices()
            ->exists(['index' => $index->getIndexName()])
            ->asBool();

        if ($check) {
            return 'update';
        }

        return 'create';
    }
}

$params = [
    'index' => 'my_index',
    'body'  => [
        'mappings' => [
            '_source'    => [
                'enabled' => true
            ],
            'properties' => [
                'first_name' => [
                    'type' => 'keyword'
                ],
                'age'        => [
                    'type' => 'integer'
                ]
            ]
        ]
    ]
];

<?php
/**
 * class ElasticIndex|Firesphere\ElasticSearch\Indexes\ElasticIndex is the base for indexing items
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Indexes;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Firesphere\ElasticSearch\Queries\Builders\QueryBuilder;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\ElasticSearch\Results\SearchResult;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\ElasticSearch\Traits\IndexTraits\BaseIndexTrait;
use Firesphere\SearchBackend\Indexes\CoreIndex;
use Firesphere\SearchBackend\Traits\LoggerTrait;
use Firesphere\SearchBackend\Traits\QueryTraits\QueryFilterTrait;
use LogicException;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;

/**
 * Base for managing a Elastic core.
 *
 * Base index settings and methods. Should be extended with at least a name for the index.
 * This is an abstract class that can not be instantiated on it's own
 *
 * @package Firesphere\Elastic\Search
 */
abstract class ElasticIndex extends CoreIndex
{
    use Extensible;
    use Configurable;
    use Injectable;
    use QueryFilterTrait;
    use BaseIndexTrait;
    use LoggerTrait;

    /**
     * @var array
     */
    protected $clientQuery;

    /**
     * @var array Classes to index
     */
    protected $class = [];

    public function __construct()
    {
        $this->client = Injector::inst()->get(ElasticCoreService::class)->getClient();

        $this->extend('onBeforeInit');
        $this->init();
        $this->extend('onAfterInit');
    }


    /**
     * Required to initialise the fields.
     * It's loaded in to the non-static properties for backward compatibility with FTS
     * Also, it's a tad easier to use this way, loading the other way around would be very
     * memory intensive, as updating the config for each item is not efficient
     */
    public function init()
    {
        $config = self::config()->get($this->getIndexName());
        if (!$config) {
            Deprecation::notice('5', 'Please set an index name and use a config yml');
        }

        if (!empty($this->getClasses())) {
            if (!$this->usedAllFields) {
                Deprecation::notice('5', 'It is advised to use a config YML for most cases');
            }

            return;
        }

        $this->initFromConfig($config);
    }


    /**
     * @param HTTPRequest|null $request
     * @return bool
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NotFoundExceptionInterface
     * @throws ServerResponseException
     */
    public function deleteIndex(HTTPRequest $request): bool
    {
        $deleteResult = false;
        if ($this->shouldClear($request) && $this->indexExists()) {
            $this->getLogger()->info(sprintf('Clearing index %s', $this->getIndexName()));
            $deleteResult = $this->client
                ->indices()
                ->delete(['index' => $this->getIndexName()])
                ->asBool();
        }

        return $deleteResult;
    }

    /**
     * @param HTTPRequest $request
     * @return bool
     */
    private function shouldClear(HTTPRequest $request): bool
    {
        $var = $request->getVar('clear');

        return !empty($var);
    }


    /**
     * @return bool
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function indexExists(): bool
    {
        return $this->getClient()
            ->indices()
            ->exists(['index' => $this->getIndexName()])
            ->asBool();
    }

    abstract public function getIndexName();

    /**
     * Get classes
     *
     * @return array
     */
    public function getClasses(): array
    {
        return $this->class;
    }

    /**
     * Generate the config from yml if possible
     * @param array|null $config
     */
    protected function initFromConfig($config): void
    {
        if (!$config || !array_key_exists('Classes', $config)) {
            throw new LogicException('No classes or config to index found!');
        }

        $this->setClasses($config['Classes']);

        // For backward compatibility, copy the config to the protected values
        // Saves doubling up further down the line
        foreach (parent::$fieldTypes as $type) {
            if (array_key_exists($type, $config)) {
                $method = 'set' . $type;
                if (method_exists($this, $method)) {
                    $this->$method($config[$type]);
                }
            }
        }
    }

    /**
     * Set the classes
     *
     * @param array $class
     * @return $this
     */
    public function setClasses($class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @param ElasticQuery $query
     * @return SearchResult
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function doSearch(ElasticQuery $query)
    {
        $this->clientQuery = QueryBuilder::buildQuery($query, $this);

        $result = $this->client->search($this->clientQuery);

        $result = new SearchResult($result, $query, $this);

        return $result;
    }

    /**
     * Add a class to index or query
     * $options is not used anymore, added for backward compatibility
     *
     * @param $class
     * @param array $options unused
     * @return $this
     */
    public function addClass($class, $options = []): self
    {
        $this->class[] = $class;

        return $this;
    }

    public function getClientQuery(): array
    {
        return $this->clientQuery;
    }

    public function setClientQuery(array $clientQuery): void
    {
        $this->clientQuery = $clientQuery;
    }
}

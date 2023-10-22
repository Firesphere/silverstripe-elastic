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

    abstract public function getIndexName(): string;

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

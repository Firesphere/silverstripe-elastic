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
use Firesphere\SearchBackend\Indexes\CoreIndex;
use Firesphere\SearchBackend\Traits\LoggerTrait;
use Firesphere\SearchBackend\Traits\QueryTraits\QueryFilterTrait;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

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
    use LoggerTrait;

    /**
     * @var array
     */
    protected $clientQuery = [];
    /**
     * @var array Fulltext fields
     */
    protected $fulltextFields = [];
    /**
     * @var array Filterable fields
     */
    protected $filterFields = [];

    /**
     * Set-up of core and fields through init
     * @throws NotFoundExceptionInterface
     */
    public function __construct()
    {
        $this->client = Injector::inst()->get(ElasticCoreService::class)->getClient();

        $this->extend('onBeforeInit');
        $this->init();
        $this->extend('onAfterInit');
    }

    /**
     * @param HTTPRequest $request
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

    /**
     * {@inheritDoc}
     * @param ElasticQuery $query
     * @return SearchResult
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function doSearch($query)
    {
        $this->clientQuery = QueryBuilder::buildQuery($query, $this);

        $result = $this->client->search($this->clientQuery);

        return new SearchResult($result, $query, $this);
    }

    /**
     * Get current client query array
     * @return array
     */
    public function getClientQuery(): array
    {
        return $this->clientQuery;
    }

    /**
     * Gives the option to completely override the client query set
     *
     * @param array $clientQuery
     * @return $this
     */
    public function setClientQuery(array $clientQuery): self
    {
        $this->clientQuery = $clientQuery;

        return $this;
    }

    /**
     * Add a filterable field
     * Compatibility stub for Solr
     *
     * @param $filterField
     * @return $this
     */
    public function addFilterField($filterField): self
    {
        $this->filterFields[] = $filterField;
        $this->addFulltextField($filterField);

        return $this;
    }

    /**
     * Add a single Fulltext field
     *
     * @param string $fulltextField
     * @param array $options
     * @return $this
     */
    public function addFulltextField($fulltextField, $options = []): self
    {
        $this->fulltextFields[] = $fulltextField;

        return $this;
    }
}

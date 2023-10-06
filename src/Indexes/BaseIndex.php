<?php

namespace Firesphere\ElasticSearch\Indexes;

use Elastic\EnterpriseSearch\Client;
use Firesphere\ElasticSearch\Queries\BaseQuery;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\ElasticSearch\Traits\IndexTraits\BaseIndexTrait;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

abstract class BaseIndex
{
    use Extensible;
    use Configurable;
    use Injectable;
    use BaseIndexTrait;

    /**
     * @var Client Comms client
     */
    protected $client;

    /**
     * @var array
     */
    protected $clientQuery;

    /**
     * @var array Classes to index
     */
    protected $class;

    public function __construct()
    {
        $this->client = (new ElasticCoreService())->getClient();
    }

    /**
     * @param BaseQuery $query
     * @return void
     */
    public function doSearch(BaseQuery $query)
    {
        $this->clientQuery = $this->buildElasticQuery($query);

        $result = $this->client->search($this->clientQuery);

        $response = $result->asArray();

        return $response['hits'];
    }

    public function buildElasticQuery(BaseQuery $query)
    {
        $search = [];
        $search['index'] = $this->getIndexName();
        $search['q'] =  $query->getTerms()[0]['text'];

        return $search;
    }

    abstract public function getIndexName();

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
     * Get classes
     *
     * @return array
     */
    public function getClasses(): array
    {
        return $this->class;
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

}

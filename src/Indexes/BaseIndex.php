<?php

namespace Firesphere\ElasticSearch\Indexes;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\ElasticSearch\Traits\IndexTraits\BaseIndexTrait;
use Firesphere\SearchBackend\Indexes\CoreIndex;
use Firesphere\SearchBackend\Results\SearchResult;
use LogicException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Deprecation;

abstract class BaseIndex extends CoreIndex
{
    use Extensible;
    use Configurable;
    use Injectable;

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
        $this->client = (new ElasticCoreService())->getClient();

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
        $this->clientQuery = $this->buildElasticQuery($query);

        $result = $this->client->search($this->clientQuery);

        $response = $result->asArray();

        return $response['hits'];
    }

    public function buildElasticQuery(ElasticQuery $query)
    {
        $search = [];
        $search['index'] = $this->getIndexName();
        $search['q'] = $query->getTerms()[0]['text'];

        return $search;
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

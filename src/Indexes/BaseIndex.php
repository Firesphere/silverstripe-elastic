<?php

namespace Firesphere\ElasticSearch\Indexes;

use Firesphere\ElasticSearch\Traits\BaseIndexTrait;
use Firesphere\ElasticSearch\Traits\GetterSetterTrait;
use Firesphere\SolrSearch\Factories\QueryComponentFactory;
use Firesphere\SolrSearch\Factories\SchemaFactory;
use LogicException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use Solarium\QueryType\Select\Query\Query;

abstract class BaseIndex
{
    use Extensible;
    use Configurable;
    use Injectable;
    use GetterSetterTrait;
    use BaseIndexTrait;
    /**
     * Field types that can be added
     * Used in init to call build methods from configuration yml
     *
     * @todo use mapping for fieldtypes
     * @array
     */
    private static $fieldTypes = [
        'FulltextFields',
        'SortFields',
        'FilterFields',
        'BoostedFields',
        'CopyFields',
        'DefaultField',
        'FacetFields',
        'StoredFields',
    ];
    /**
     * {@link SchemaFactory}
     *
     * @var SchemaFactory Schema factory for generating the schema
     */
    protected $schemaFactory;
    /**
     * {@link QueryComponentFactory}
     *
     * @var QueryComponentFactory Generator for all components
     */
    protected $queryFactory;
    /**
     * @var array The query terms as an array
     */
    protected $queryTerms = [];
    /**
     * @var Query Query that will hit the client
     */
    protected $clientQuery;
    /**
     * @var bool Signify if a retry should occur if nothing was found and there are suggestions to follow
     */
    private $retry = false;


    public function __construct()
    {
        // Set up the schema service, only used in the generation of the schema
        /** @var SchemaFactory $schemaFactory */
        $schemaFactory = Injector::inst()->get(SchemaFactory::class, false);
        $schemaFactory->setIndex($this);
        $schemaFactory->setStore(Director::isDev());
        $this->schemaFactory = $schemaFactory;
        $this->queryFactory = Injector::inst()->get(QueryComponentFactory::class, false);

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
        foreach (self::$fieldTypes as $type) {
            if (array_key_exists($type, $config)) {
                $method = 'set' . $type;
                $this->$method($config[$type]);
            }
        }
    }

    /**
<<<<<<< Updated upstream
     * Name of this index.
     *
     * @return string
     */
    abstract public function getIndexName();
}
=======
     * Default returns a SearchResult. It can return an ArrayData if FTS Compat is enabled
     *
     * @param BaseQuery $query
     * @return SearchResult|ArrayData|mixed
     * @throws HTTPException
     * @throws ValidationException
     * @throws ReflectionException
     * @throws Exception
     */
    public function doSearch(BaseQuery $query)
    {
        SiteState::alterQuery($query);
        // Build the actual query parameters
        $this->clientQuery = $this->buildSolrQuery($query);
        // Set the sorting
        $this->clientQuery->addSorts($query->getSort());

        $this->extend('onBeforeSearch', $query, $this->clientQuery);

        try {
            $result = $this->client->select($this->clientQuery);
        } catch (Exception $error) {
            // @codeCoverageIgnoreStart
            $logger = new SolrLogger();
            $logger->saveSolrLog('Query');
            throw $error;
            // @codeCoverageIgnoreEnd
        }

        // Handle the after search first. This gets a raw search result
        $this->extend('onAfterSearch', $result);
        $searchResult = new SearchResult($result, $query, $this);
        if ($this->doRetry($query, $result, $searchResult)) {
            // We need to override the spellchecking with the previous spellcheck
            // @todo refactor this to a cleaner way
            $collation = $result->getSpellcheck();
            $retryResults = $this->spellcheckRetry($query, $searchResult);
            $this->retry = false;
            return $retryResults->setCollatedSpellcheck($collation);
        }

        // And then handle the search results, which is a useable object for SilverStripe
        $this->extend('updateSearchResults', $searchResult);

        return $searchResult;
    }

}
>>>>>>> Stashed changes

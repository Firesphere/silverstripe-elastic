<?php

namespace Firesphere\ElasticSearch\Indexes;

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

    /**
     * Field types that can be added
     * Used in init to call build methods from configuration yml
     *
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

}
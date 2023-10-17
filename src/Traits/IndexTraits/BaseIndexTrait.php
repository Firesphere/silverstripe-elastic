<?php
/**
 * Trait BaseIndexTrait|Firesphere\ElasticSearch\Traits\BaseIndexTrait Used to extract methods from the
 * {@link \Firesphere\ElasticSearch\Indexes\ElasticIndex} to make the code more readable
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Traits\IndexTraits;

use Elastic\Elasticsearch\Client;
use Firesphere\SearchBackend\Indexes\CoreIndex;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBString;

/**
 * Trait ElasticIndexTrait
 * Getters and Setters for the ElasticIndex
 *
 * @package Firesphere\Elastic\Search
 */
trait BaseIndexTrait
{
    /**
     * @var Client Query client
     */
    protected $client;
    /**
     * @var array Facet fields
     */
    protected $facetFields = [];
    /**
     * @var array Fulltext fields
     */
    protected $fulltextFields = [];
    /**
     * @var array Filterable fields
     */
    protected $filterFields = [];
    /**
     * @var array Sortable fields
     */
    protected $sortFields = [];
    /**
     * @var array Stored fields
     */
    protected $storedFields = [];

    /**
     * usedAllFields is used to determine if the addAllFields method has been called
     * This is to prevent a notice if there is no yml.
     *
     * @var bool
     */
    protected $usedAllFields = false;

    /**
     * Add a field to sort on
     *
     * @param $sortField
     * @return $this
     */
    public function addSortField($sortField): self
    {
        $this->addFulltextField($sortField);
        $this->sortFields[] = $sortField;

        return $this;
    }

    /**
     * Get the fulltext fields
     *
     * @return array
     */
    public function getFulltextFields(): array
    {
        return array_values(
            array_unique(
                $this->fulltextFields
            )
        );
    }

    /**
     * Set the fulltext fields
     *
     * @param array $fulltextFields
     * @return $this
     */
    public function setFulltextFields($fulltextFields): self
    {
        $this->fulltextFields = $fulltextFields;

        return $this;
    }

    /**
     * Get the filter fields
     *
     * @return array
     */
    public function getFilterFields(): array
    {
        return $this->filterFields;
    }

    /**
     * Set the filter fields
     *
     * @param array $filterFields
     * @return $this
     */
    public function setFilterFields($filterFields): self
    {
        $this->filterFields = $filterFields;
        foreach ($filterFields as $filterField) {
            $this->addFulltextField($filterField);
        }

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

    /**
     * Get the sortable fields
     *
     * @return array
     */
    public function getSortFields(): array
    {
        return $this->sortFields;
    }

    /**
     * Set/override the sortable fields
     *
     * @param array $sortFields
     * @return $this
     */
    public function setSortFields($sortFields): self
    {
        $this->sortFields = $sortFields;
        foreach ($sortFields as $sortField) {
            $this->addFulltextField($sortField);
        }

        return $this;
    }

    /**
     * Add all text-type fields to the given index
     *
     * @throws ReflectionException
     */
    public function addAllFulltextFields()
    {
        $this->addAllFieldsByType(DBString::class);
    }

    /**
     * Add all database-backed text fields as fulltext searchable fields.
     *
     * For every class included in the index, examines those classes and all parent looking for "DBText" database
     * fields (Varchar, Text, HTMLText, etc) and adds them all as fulltext searchable fields.
     *
     * Note, there is no check on boosting etc. That needs to be done manually.
     *
     * @param string $dbType
     * @throws ReflectionException
     */
    protected function addAllFieldsByType($dbType = DBString::class): void
    {
        $this->usedAllFields = true;
        $classes = $this->getClasses();
        foreach ($classes as $key => $class) {
            $fields = DataObject::getSchema()->databaseFields($class, true);

            $this->addFulltextFieldsForClass($fields, $dbType);
        }
    }

    /**
     * This trait requires classes to be set, so getClasses can be called.
     *
     * @return array
     */
    abstract public function getClasses(): array;

    /**
     * Add all fields of a given type to the index
     *
     * @param array $fields The fields on the DataObject
     * @param string $dbType Class type the reflection should extend
     * @throws ReflectionException
     */
    protected function addFulltextFieldsForClass(array $fields, $dbType = DBString::class): void
    {
        foreach ($fields as $field => $type) {
            $pos = strpos($type, '(');
            if ($pos !== false) {
                $type = substr($type, 0, $pos);
            }
            $conf = Config::inst()->get(Injector::class, $type);
            $ref = new ReflectionClass($conf['class']);
            if ($ref->isSubclassOf($dbType)) {
                $this->addFulltextField($field);
            }
        }
    }

    /**
     * Add all date-type fields to the given index
     *
     * @throws ReflectionException
     */
    public function addAllDateFields()
    {
        $this->addAllFieldsByType(DBDate::class);
    }

    /**
     * Set the fields to use for faceting
     * @param $fields
     * @return $this
     */
    public function setFacetFields($fields)
    {
        foreach ($fields as $field => $option) {
            $this->addFacetField($field, $option);
        }

        return $this;
    }

    /**
     * Add a facet field
     *
     * @param $field
     * @param array $options
     * @return $this
     */
    public function addFacetField($field, $options)
    {
        $this->facetFields[$field] = $options;

        if (!in_array($options['Field'], $this->getFulltextFields(), true)) {
            $this->addFulltextField($options['Field']);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getFacetFields()
    {
        return $this->facetFields;
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
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @return array
     */
    public function getStoredFields(): array
    {
        return $this->storedFields;
    }

    /**
     * Stub to be compatible with Solr. Elastic stores everything anyway
     * @param array $storedFields
     * @return $this
     */
    public function setStoredFields(array $storedFields)
    {
        $this->storedFields = $storedFields;
        foreach ($storedFields as $storedField) {
            $this->addFulltextField($storedField);
        }

        return $this;
    }
}

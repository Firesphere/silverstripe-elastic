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

/**
 * Trait ElasticIndexTrait
 * Getters and Setters for the ElasticIndex
 *
 * @package Firesphere\Elastic\Search
 */
trait BaseIndexTrait
{
    /**
     * @var array Fulltext fields
     */
    protected $fulltextFields = [];
    /**
     * @var array Filterable fields
     */
    protected $filterFields = [];

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
}

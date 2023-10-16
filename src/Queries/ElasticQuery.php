<?php
/**
 * class ElasticQuery|Firesphere\ElasticSearch\Queries\ElasticQuery Base of an Elastic Query
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Queries;

use Firesphere\SearchBackend\Queries\BaseQuery;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class BaseQuery is the base of every query executed.
 *
 * Build a query to execute agains Elastic. Uses as simle as possible an interface.
 *
 * @package Firesphere\Elastic\Search
 */
class ElasticQuery extends BaseQuery
{
    use Injectable;

    /**
     * @var array Sorting settings
     */
    protected $sort = [];
    /**
     * @var bool Enable spellchecking?
     */
    protected $spellcheck = true;

    /**
     * @var array Filters to use/apply
     */
    protected $filters = [];

    /**
     * @var array Filters that are not exclusive
     */
    protected $orFilters = [];
    /**
     * @var int Minimum results a facet query has to have
     */
    protected $facetsMinCount = 1;
    /**
     * @var array Search terms
     */
    protected $terms = [];

    /**
     * @var array
     */
    protected $boostedFields = [];

    /**
     * @var bool
     */
    protected $highlight = true;

    /**
     * Get the sort fields
     *
     * @return array
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * Set the sort fields
     *
     * @param array $sort
     * @return $this
     */
    public function setSort($sort): self
    {
        $this->sort = $sort;

        return $this;
    }


    /**
     * Get the search terms
     *
     * @return array
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
     * Set the search tearms
     *
     * @param array $terms
     * @return $this
     */
    public function setTerms($terms): self
    {
        $this->terms = $terms;

        return $this;
    }

    /**
     * Each boosted query needs a separate addition!
     * e.g. $this->addTerm('test', ['MyField', 'MyOtherField'], 3)
     * followed by
     * $this->addTerm('otherTest', ['Title'], 5);
     *
     * If you want a generic boost on all terms, use addTerm only once, but boost on each field
     *
     * The fields parameter is used to boost on
     * // @param string $term Term to search for
     * @param array $fields fields to boost on
     * @param int $boost Boost value
     * @param bool|float $fuzzy True or a value to the maximum amount of iterations
     * @return $this
     * @todo fix this to not be Solr~ish
     * For generic boosting, use @addBoostedField($field, $boost), this will add the boost at Index time
     *
     */
    public function addTerm(string $term, array $fields = [], int $boost = 1, $fuzzy = null): self
    {
        $this->terms[] = [
            'text'   => $term,
            'fields' => $fields,
            'boost'  => $boost
        ];

        return $this;
    }

    /**
     * @param string $key Field to apply filter on
     * @param string|array $value Value(s) to filter on
     * @return BaseQuery
     */
    public function addFilter($key, $value): BaseQuery
    {
        $this->filters[$key] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @param array $filters
     * @return BaseQuery
     */
    public function setFilters(array $filters): BaseQuery
    {
        $this->filters = $filters;

        return $this;
    }

    public function getOrFilters(): array
    {
        return $this->orFilters;
    }

    public function setOrFilters(array $orFilters): void
    {
        $this->orFilters = $orFilters;
    }

    public function addOrFilters(string $key, string $orFilters): void
    {
        $this->orFilters[$key] = $orFilters;
    }

    public function getBoostedFields(): array
    {
        return $this->boostedFields;
    }

    public function setBoostedFields(array $boostedFields): void
    {
        $this->boostedFields = $boostedFields;
    }

    public function addBoostedField($key, $value)
    {
        $this->boostedFields[$key] = $value;
    }

    public function isHighlight(): bool
    {
        return $this->highlight;
    }

    public function setHighlight(bool $highlight): void
    {
        $this->highlight = $highlight;
    }
}

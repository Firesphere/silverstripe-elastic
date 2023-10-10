<?php

namespace Firesphere\ElasticSearch\Queries;

use Firesphere\SearchBackend\Queries\BaseQuery;
use SilverStripe\Core\Injector\Injectable;

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
     * @var int Minimum results a facet query has to have
     */
    protected $facetsMinCount = 1;
    /**
     * @var array Search terms
     */
    protected $terms = [];

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
    public function addTerm(string $term, array $fields = [], int $boost = 0, $fuzzy = null): self
    {
        $this->terms[] = [
            'text' => $term,
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

    public function getFiltersForMatch(): array
    {
        $return = [];
        foreach ($this->filters as $field => $value) {
            $this->toMatch($field, $value, $return);
        }

        return $return;
    }

    private function toMatch($key, $value, &$return)
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                $this->toMatch($key, $val, $return);
            }
        } else {
            $return[] = ['match' => [$key => $value]];
        }
    }

}

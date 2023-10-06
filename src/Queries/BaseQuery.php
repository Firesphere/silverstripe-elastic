<?php

namespace Firesphere\ElasticSearch\Queries;

use Firesphere\ElasticSearch\Traits\IndexTraits\BaseIndexTrait;
use SilverStripe\Core\Injector\Injectable;

class BaseQuery
{
    use Injectable;

    /**
     * @var int Pagination start
     */
    protected $start = 0;
    /**
     * @var int Total rows to display
     */
    protected $rows = 10;
    /**
     * @var array Sorting settings
     */
    protected $sort = [];
    /**
     * @var bool Enable spellchecking?
     */
    protected $spellcheck = true;
    /**
     * @var int Minimum results a facet query has to have
     */
    protected $facetsMinCount = 1;
    /**
     * @var array Search terms
     */
    protected $terms = [];
    /**
     * @var array Highlighted items
     */
    protected $highlight = [];


    /**
     * Get the offset to start
     *
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * Set the offset to start
     *
     * @param int $start
     * @return $this
     */
    public function setStart($start): self
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get the rows to return
     *
     * @return int
     */
    public function getRows(): int
    {
        return $this->rows;
    }

    /**
     * Set the rows to return
     *
     * @param int $rows
     * @return $this
     */
    public function setRows($rows): self
    {
        $this->rows = $rows;

        return $this;
    }


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
     *
     * For generic boosting, use @addBoostedField($field, $boost), this will add the boost at Index time
     *
     * @param string $term Term to search for
     * @param array $fields fields to boost on
     * @param int $boost Boost value
     * @param bool|float $fuzzy True or a value to the maximum amount of iterations
     * @return $this
     */
    public function addTerm(string $term, array $fields = [], int $boost = 0, $fuzzy = null): self
    {
        $this->terms[] = [
            'text'   => $term,
            'fields' => $fields,
            'boost'  => $boost,
            'fuzzy'  => $fuzzy,
        ];

        return $this;
    }

}

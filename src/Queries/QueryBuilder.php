<?php
/**
 * class QueryBuilder|Firesphere\ElasticSearch\Queries\Builders\QueryBuilder Build the Elastic query array
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Queries\Builders;

use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\SearchBackend\Indexes\CoreIndex;
use Firesphere\SearchBackend\Interfaces\QueryBuilderInterface;
use Firesphere\SearchBackend\Queries\BaseQuery;
use SilverStripe\Core\ClassInfo;

/**
 * Class QueryBuilder
 *
 * Build/construct an array to send to Elastic to query the results.
 *
 * @package Firesphere\Elastic\Search
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * @var ElasticQuery
     */
    protected $query;

    /**
     * @var ElasticIndex
     */
    protected $index;

    /**
     * @param BaseQuery $query
     * @param ElasticIndex $index
     * @return array
     */
    public static function buildQuery(BaseQuery $query, CoreIndex $index): array
    {
        $self = self::init($query, $index);
        $filters = $self->getFilters($index, $query);
        $terms = $self->getUserQuery($query);
        $highlights = $self->getHighlighter();
        $suggests = $self->getSuggestTermList();
        $aggregates = $self->getAggregates();
        $sort = $self->getSort();
        $body = [];
        if (count($terms)) {
            $body['query']['bool'] = $terms;
        }
        if (count($filters)) {
            $body['query']['bool'] += $filters;
        }
        if (count($highlights)) {
            $body['highlight'] = $highlights;
        }
        if (count($suggests)) {
            $body['suggest'] = $suggests;
        }
        if (count($aggregates)) {
            $body['aggs'] = $aggregates;
        }
        if (count($sort)) {
            $body['sort'] = $sort;
        }

        return [
            'index' => $index->getIndexName(),
            'from'  => $query->getStart(),
            'size'  => $query->getRows() * 2, // To be on the safe side
            'body'  => $body
        ];
    }

    /**
     * @param ElasticQuery $query
     * @param ElasticIndex $index
     * @return self
     */
    protected static function init(ElasticQuery $query, ElasticIndex $index): self
    {
        $self = new self();
        $self->setIndex($index);
        $self->setQuery($query);

        return $self;
    }

    /**
     * @param mixed $index
     */
    public function setIndex($index): void
    {
        $this->index = $index;
    }

    /**
     * @param mixed $query
     */
    public function setQuery($query): void
    {
        $this->query = $query;
    }

    /**
     * Build the `OR` and `AND` filters
     * @param ElasticIndex $index
     * @param ElasticQuery $query
     * @return array[]
     */
    private function getFilters(ElasticIndex $index, ElasticQuery $query): array
    {
        return [
            'filter' => [
                'bool' => [
                    'must'   => $this->getAndFilters($index, $query),
                    'should' => $this->getOrFilters($query)
                ],
            ]
        ];
    }

    /**
     * Required must-be filters if they're here.
     * @param ElasticIndex $index
     * @param ElasticQuery $query
     * @return array[]
     */
    private function getAndFilters(ElasticIndex $index, ElasticQuery $query): array
    {
        // Default,
        $filters = [
            [
                'terms' => [
                    'ViewStatus' => $index->getViewStatusFilter(),
                ]
            ]
        ];
        if (count($query->getFilters())) {
            foreach ($query->getFilters() as $key => $value) {
                $value = is_array($value) ?: [$value];
                $filters[] = ['terms' => [$key => $value]];
            }
        }

        return $filters;
    }

    /**
     * Create the "should" filter, that is OR instead of AND
     * @param ElasticQuery $query
     * @return array
     */
    private function getOrFilters(ElasticQuery $query): array
    {
        $filters = [];
        if (count($query->getOrFilters())) {
            foreach ($query->getOrFilters() as $key => $value) {
                $value = is_array($value) ?: [$value];
                $filters[] = ['terms' => [$key => $value]];
            }
        }

        return $filters;
    }

    /**
     * this allows for multiple search terms to be entered
     * @param ElasticQuery|BaseQuery $query
     * @return array
     */
    private function getUserQuery(ElasticQuery|BaseQuery $query): array
    {
        $q = [];
        $terms = $query->getTerms();
        // Until wildcards work, just set it to match
        $type = 'match';
        if (!count($terms)) {
            $terms = ['text' => '*'];
        }
        foreach ($terms as $term) {
            $q['must'][] = [
                $type => [
                    '_text' => $term['text']
                ]
            ];
            $q = $this->getFieldBoosting($term, $type, $q);
        }

        return $q;
    }

    /**
     * @param mixed $term
     * @param string $type
     * @param array $q
     * @return array
     */
    private function getFieldBoosting(mixed $term, string $type, array $q): array
    {
        $shoulds = [];
        $queryBoosts = $this->query->getBoostedFields();
        if ($term['boost'] > 1 && count($term['fields'])) {
            foreach ($term['fields'] as $field) {
                $shoulds[] = $this->addShould($type, $field, $term['text'], $term['boost']);
            }
        }
        foreach ($queryBoosts as $field => $boost) {
            $shoulds[] = $this->addShould($type, $field, $term['text'], $boost);
        }
        if (count($shoulds)) {
            $q['should'] = $shoulds;
        }

        return $q;
    }

    /**
     * @param string $type
     * @param string $field
     * @param $text
     * @param int $boost
     * @return array
     */
    private function addShould(string $type, string $field, $text, int $boost): array
    {
        $should = [
            $type => [
                $field => [
                    'query' => $text,
                    'boost' => $boost
                ]
            ]
        ];

        return $should;
    }

    private function getHighlighter(): array
    {
        if ($this->query->isHighlight()) {
            $highlights = [];
            foreach ($this->index->getFulltextFields() as $field) {
                $highlights[$field] = ['type' => 'unified'];
            }

            return ['fields' => $highlights];
        }

        return [];
    }

    private function getSuggestTermList()
    {
        $terms = $this->query->getTerms();
        $suggest = [];
        $base = [
            'term' => ['field' => '_text']
        ];
        foreach ($terms as $j => $term) {
            $base['text'] = $term['text'];
            $suggest[$j . '-fullterm'] = $base;
            if (str_contains($term['text'], ' ')) {
                $termArray = explode(' ', $term['text']);
                foreach ($termArray as $i => $word) {
                    $base['text'] = $word;
                    $suggest[$i . '-partterm'] = $base;
                }
            }
        }

        return $suggest;
    }

    /**
     * Build the query part for aggregation/faceting
     *
     * @return array
     */
    private function getAggregates()
    {
        $aggregates = [];

        $facets = $this->index->getFacetFields();

        foreach ($facets as $class => $facet) {
            $shortClass = ClassInfo::shortName($facet['BaseClass']);
            $field = sprintf('%s.%s', $shortClass, $facet['Field']);
            $aggregates[$facet['Title']] = [
                'terms' => [
                    'field' => $field
                ]

            ];
        }

        return $aggregates;
    }

    private function getSort()
    {
        return $this->query->getSort();
    }
}

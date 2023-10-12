<?php

namespace Firesphere\ElasticSearch\Queries\Builders;

use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\SearchBackend\Indexes\CoreIndex;
use Firesphere\SearchBackend\Interfaces\QueryBuilderInterface;
use Firesphere\SearchBackend\Queries\BaseQuery;

class QueryBuilder implements QueryBuilderInterface
{
    /**
     * @param ElasticQuery $query
     * @param ElasticIndex $index
     * @return array
     */
    public static function buildQuery(BaseQuery $query, CoreIndex $index): array
    {
        $filters = self::getFilters($index, $query);
        $orFilters = self::getOrFilters($query);
        // Always primarily search against the _text field, that's where all content is
        $terms = ['must' => self::getUserQuery($query)]; // There's always a term
        if (count($filters)) {
            $filters = ['filter' => ['bool' => ['must' => $filters]]];
            $terms = array_merge($terms, $filters);
        }
        if (count($orFilters)) {
            $terms['filter']['bool']['should'] = $orFilters;
        }

        return [
            'index' => $index->getIndexName(),
            'from'  => $query->getStart(),
            'size'  => $query->getRows(),
            'body'  => [
                'query' => [
                    'bool' => $terms,
                ],
            ]
        ];
    }

    /**
     * Required must-be filters if they're here.
     * @param CoreIndex|ElasticIndex $index
     * @param ElasticQuery|BaseQuery $query
     * @return array[]
     */
    private static function getFilters(CoreIndex|ElasticIndex $index, ElasticQuery|BaseQuery $query): array
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
     * @param BaseQuery $query
     * @return array
     */
    private static function getOrFilters(BaseQuery $query)
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
    public static function getUserQuery(ElasticQuery|BaseQuery $query): array
    {
        $q = [];
        foreach ($query->getTerms() as $term) {
            if (!count($term['fields'])) {
                $q[] = ['match' => ['_text' => $term['text']]];
            } else {
                foreach ($term['fields'] as $field) {
                    $q[] = ['match' => [$field => $term['text']]];
                }
            }
        }

        return $q;
    }
}

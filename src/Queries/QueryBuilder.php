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
     * @var ElasticQuery
     */
    protected $query;

    /**
     * @var ElasticIndex
     */
    protected $index;

    /**
     * @param ElasticQuery $query
     * @param ElasticIndex $index
     * @return array
     */
    public static function buildQuery(BaseQuery $query, CoreIndex $index): array
    {
        $self = self::init($query, $index);
        $filters = $self->getFilters($index, $query);
        $terms = $self->getUserQuery($query); // There's always a term
        $terms = array_merge($terms, $filters);

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
        $type = 'match';
        if (!count($terms)) {
            $type = 'wildcard';
            $terms = ['*'];
        }
        foreach ($terms as $term) {
            $q['must'][] = ['match' => ['_text' => $term['text']]];
            if ($type !== 'wildcard') {
                $q = $this->getFieldBoosting($term, $type, $q);
            }
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
}

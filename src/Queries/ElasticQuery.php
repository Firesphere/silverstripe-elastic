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
     * @inheritDoc
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
}

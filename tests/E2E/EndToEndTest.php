<?php

namespace Firesphere\ElasticSearch\Tests;

use App\src\SearchIndex;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\ElasticSearch\Results\SearchResult;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;

/**
 * @package Firesphere\Elastic\Tests
 */
class EndToEndTest extends SapphireTest
{
    public function testSearching()
    {
        /** @var ElasticIndex $index */
        $index = new SearchIndex();
        $query = new ElasticQuery();
        $query->addTerm('Test');
        $results = $index->doSearch($query);
        $this->assertArrayHasKey('index', $index->getClientQuery());
        $this->assertGreaterThan(0, $results->getTotalItems());
        $this->assertInstanceOf(SearchResult::class, $results);
        $this->assertInstanceOf(PaginatedList::class, $results->getPaginatedMatches());
        $this->assertInstanceOf(ArrayList::class, $results->getSpellcheck());
    }
}

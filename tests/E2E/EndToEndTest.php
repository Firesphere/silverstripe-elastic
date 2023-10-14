<?php

namespace Firesphere\ElasticSearch\Tests;

use App\src\SearchIndex;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\ElasticSearch\Results\SearchResult;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;

class EndToEndTest extends SapphireTest
{
    protected function setUp(): void
    {
        // Build defaults
        $adm = DatabaseAdmin::singleton();
        $adm->setRequest(new HTTPRequest('GET', 'dev/build', ['quiet' => true]));
        $adm->doBuild(true, true, true);
    }

    public function testSearching()
    {
        /** @var ElasticIndex $index */
        $index = new SearchIndex();
        $query = new ElasticQuery();
        $query->addTerm('Silverstripe');
        $results = $index->doSearch($query);
        $this->assertArrayHasKey('index', $index->getClientQuery());
        $this->assertGreaterThan(0, $results->getTotalItems());
        $this->assertInstanceOf(SearchResult::class, $results);
        $this->assertInstanceOf(PaginatedList::class, $results->getPaginatedMatches());
        $this->assertInstanceOf(ArrayList::class, $results->getMatches());
        $this->assertEquals($results->getTotalItems(), $results->getPaginatedMatches()->getTotalItems());
        $this->assertInstanceOf(DataObject::class, $results->getMatches()->first());
    }
}
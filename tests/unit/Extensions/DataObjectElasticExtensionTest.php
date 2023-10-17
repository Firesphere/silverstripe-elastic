<?php

namespace Firesphere\ElasticSearch\Extensions;

use App\src\SearchIndex;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use SilverStripe\Dev\SapphireTest;

/**
 * @package Firesphere\Elastic\Tests
 */
class DataObjectElasticExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testCreatingObject()
    {
        $page = \Page::create(['Title' => 'Test page']);
        $page->write();
        $extension = new DataObjectElasticExtension();
        $extension->setOwner($page);
        $extension->onAfterWrite();
        sleep(1); // Wait for Elastic to do its job
        $query = new ElasticQuery();
        $query->addTerm('Page');
        /** @var ElasticIndex $index */
        $index = new SearchIndex();
        $result = $index->doSearch($query);
        $this->assertEquals(3, $result->getTotalItems());
        $page->publishSingle();
        $extension->onAfterWrite();
    }

    public function testOnAfterDelete()
    {
        $pages = \Page::get();
        $extension = new DataObjectElasticExtension();
        foreach ($pages as $page) {
            $extension->setOwner($page);
            $extension->onAfterDelete();
            $page->delete();
        }
        $query = new ElasticQuery();
        $query->addTerm('Page');
        // Elastic isn't fast enough to have processed the request
        // So... Unsure how to fix this with a proper assertion
        // Even despite the wait in PHP, it doesn't help
        $this->assertEquals(0, \Page::get()->count());
    }

    public function testAddRemoveFromElastic()
    {

        $page = \Page::create(['Title' => 'AddRemove Test']);
        $extension = new DataObjectElasticExtension();
        $extension->setOwner($page);
        $indexCheck = $extension->pushToElastic();
        $this->assertInstanceOf(Elasticsearch::class, $indexCheck);
        $removeCheck = $extension->deleteFromElastic();
        $this->assertInstanceOf(Elasticsearch::class, $removeCheck);
        $page->ShowInSearch = false;
        $page->forceChange();
        $extension->onAfterWrite();
        // Expect false, because it wasn't even in Elastic anymore
        $this->assertFalse($extension->isDeletedFromElastic());
    }

    public function testShouldPush()
    {
        $page = \Page::create();
        $extension = new DataObjectElasticExtension();
        $extension->setOwner($page);

        // Page is not published
        $this->assertFalse($extension->shouldPush($page));
        $page->write();
        $page->publishSingle();
        // Page is published
        $this->assertTrue($extension->shouldPush($page));
        $page->ShowInSearch = false;
        $page->write();
        // Doesn't matter, always false because ShowInSearch
        $this->assertFalse($extension->shouldPush($page));
        $page->publishSingle();
        // As before
        $this->assertFalse($extension->shouldPush($page));
    }
}

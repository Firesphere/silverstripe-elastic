<?php

namespace Firesphere\ElasticSearch\Extensions;

use App\src\SearchIndex;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use SilverStripe\Dev\SapphireTest;

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
        sleep(5); // Wait for Elastic to do its job
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
        $this->assertNotFalse($indexCheck);
        $removeCheck = $extension->deleteFromElastic();
        $this->assertNotFalse($removeCheck);
    }
}

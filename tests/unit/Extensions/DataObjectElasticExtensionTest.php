<?php

namespace Firesphere\ElasticSearch\Extensions;

use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use App\src\SearchIndex;

class DataObjectElasticExtensionTest extends SapphireTest
{

    public function testCreatingObject()
    {
        /** @var ElasticCoreService $service */
        $service = Injector::inst()->get(ElasticCoreService::class);

        $page = \Page::create(['Title' => 'Test page']);
        $page->write();
        $extension = new DataObjectElasticExtension();
        $extension->setOwner($page);
        $extension->onAfterWrite();
        sleep(5); // Wait for Elastic to do its job
        $query = new ElasticQuery();
        $query->addTerm($page->ClassName . '-' . $page->ID);
        /** @var ElasticIndex $index */
        $index = new SearchIndex();
        $result = $index->doSearch($query);
        $this->assertEquals(3, $result->getTotalItems());
        $page->publishSingle();
        $extension->onAfterWrite();
        sleep(5);
        $index->doSearch($query);
        $this->assertEquals(1, $result->getTotalItems());
    }
}
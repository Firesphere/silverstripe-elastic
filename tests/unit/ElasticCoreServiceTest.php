<?php

namespace Firesphere\ElasticSearch\Tests;

use app\src\SearchIndex;
use Elastic\Elasticsearch\Client;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class ElasticCoreServiceTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testConstruct()
    {
        $service = Injector::inst()->get(ElasticCoreService::class);
        $this->assertInstanceOf(Client::class, $service->getClient());
        $this->assertNotEmpty($service->getValidIndexes());
    }


    public function testUpdateIndex()
    {
        /** @var ElasticCoreService $service */
        $service = Injector::inst()->get(ElasticCoreService::class);
        $service->updateIndex(new SearchIndex(), Page::get());
        $service->getClient()->count(['index' => 'search-testindex']);
    }
}

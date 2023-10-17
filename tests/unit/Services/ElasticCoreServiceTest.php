<?php

namespace Firesphere\ElasticSearch\Tests;

use app\src\SearchIndex;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * @package Firesphere\Elastic\Tests
 */
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
        \Page::create(['Title' => 'Home'])->write();
        $docs = $service->updateIndex(new SearchIndex(), \Page::get(), true);
        $count = $service->getClient()->count(['index' => 'search-testindex']);
        $this->assertInstanceOf(Elasticsearch::class, $count);
        $this->assertGreaterThan(0, count($docs));
    }
}

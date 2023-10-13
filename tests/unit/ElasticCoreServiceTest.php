<?php

namespace Firesphere\ElasticSearch\Tests;

use app\src\SearchIndex;
use Elastic\Elasticsearch\Client;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DatabaseAdmin;

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
        $service->updateIndex(new SearchIndex(), \app\src\Page::get());
        $service->getClient()->count(['index' => 'search-testindex']);
    }


    protected function setUp(): void
    {
        parent::setUp();
        $request = new HTTPRequest('GET', 'dev/build', ['quiet' => true, 'flush' => 1]);
        DatabaseAdmin::singleton()->setRequest($request)->build();
    }
}

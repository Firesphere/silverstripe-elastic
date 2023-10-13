<?php

namespace Firesphere\ElasticSearch\Tests;

use Elastic\Elasticsearch\Client;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Dev\SapphireTest;

class ElasticCoreServiceTest extends SapphireTest
{
    public function testConstruct()
    {
        $service = Injector::inst()->get(ElasticCoreService::class);
        $this->assertInstanceOf(Client::class, $service->getClient());
        $this->assertNotEmpty($service->getValidIndexes());
    }

    protected function setUp(): void
    {
        parent::setUp();
        ClassLoader::inst()->init(1);
    }
}

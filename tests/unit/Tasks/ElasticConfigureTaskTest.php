<?php

namespace Firesphere\ElasticSearch\Tests\unit\Tasks;

use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\ElasticSearch\Tasks\ElasticConfigureTask;
use SilverStripe\Dev\SapphireTest;

class ElasticConfigureTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRun()
    {
        $task = new ElasticConfigureTask();

        $this->assertInstanceOf(ElasticCoreService::class, $task->getService());
    }
}

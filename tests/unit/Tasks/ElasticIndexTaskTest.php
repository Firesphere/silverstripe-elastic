<?php

namespace Firesphere\ElasticSearch\Tasks;

use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

class ElasticIndexTaskTest extends SapphireTest
{
    public function testConstruct()
    {
        $task = new ElasticIndexTask();
        $this->assertInstanceOf(ElasticCoreService::class, $task->getService());
        $this->assertInstanceOf(LoggerInterface::class, $task->getLogger());

        $this->assertFalse($task->isDebug());
        $task->setDebug(true);
        $this->assertTrue($task->isDebug());
    }

    public function testRun()
    {
        $task = new ElasticIndexTask();
        $request = new HTTPRequest('GET', 'dev/tasks/ElasticIndexTask');
        $result = $task->run($request);

        $this->assertGreaterThan(0, $result);
    }
}

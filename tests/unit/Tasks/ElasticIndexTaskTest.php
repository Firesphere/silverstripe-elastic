<?php

namespace Firesphere\ElasticSearch\Tasks;

use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

/**
 * @package Firesphere\Elastic\Tests
 */
class ElasticIndexTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

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
        $page = \Page::create(['Title' => 'Testing title']);
        $page->write();
        $page->publishSingle();
        $task = new ElasticIndexTask();
        $request = new HTTPRequest('GET', 'dev/tasks/ElasticIndexTask');
        $result = $task->run($request);

        $this->assertGreaterThan(0, $result);
        $this->assertinstanceOf(ElasticIndex::class, $task->getIndex());
    }
}

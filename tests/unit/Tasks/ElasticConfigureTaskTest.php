<?php

namespace Firesphere\ElasticSearch\Tests\unit\Tasks;

use App\src\SearchIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\ElasticSearch\Tasks\ElasticConfigureTask;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

class ElasticConfigureTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRun()
    {
        $task = new ElasticConfigureTask();

        $this->assertInstanceOf(ElasticCoreService::class, $task->getService());

        $task->run(new HTTPRequest('GET', 'dev/tasks/ElasticConfigureTask', ['istest' => self::$is_running_test]));

        $this->assertNotContains(false, $task->result);

        // Same, but with clearing
        $task->run(new HTTPRequest('GET', 'dev/tasks/ElasticConfigureTask', ['istest' => self::$is_running_test, 'clear' => true]));

        $this->assertNotContains(false, $task->result);

    }

    public function testConfigureIndex()
    {
        $index = new SearchIndex();
        $task = new ElasticConfigureTask();
        $result = $task->configureIndex($index);

        $this->assertTrue($result->asBool());
    }
}

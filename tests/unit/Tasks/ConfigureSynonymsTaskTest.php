<?php

namespace Firesphere\ElasticSearch\Tasks;

use Firesphere\ElasticSearch\Models\SynonymSet;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * @package Firesphere\Elastic\Tests
 */
class ConfigureSynonymsTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRun()
    {
        (new SynonymSet())->requireDefaultRecords();
        $request = new HTTPRequest('GET', 'dev/tasks/ElasticSynonymTask');
        $task = new ElasticConfigureSynonymsTask();

        $task->run($request);
        /** @var ElasticCoreService $service */
        $service = Injector::inst()->get(ElasticCoreService::class);
        $set = SynonymSet::get()->first();
        $result = $service->getClient()->synonyms()->getSynonym(['id' => $set->Key])->asArray();
        $this->assertGreaterThan(0, $result);
        // Using the defaults, there are at least 21000 US<=>UK synonyms/spelling differences
        $this->assertGreaterThan(21000, $result['count']);
        $firstBaseSynonym = [
            'id'       => 'base-AWOL',
            'synonyms' => 'AWOL,awol'
        ];
        // Expect the first synonym
        $this->assertEquals($firstBaseSynonym, $result['synonyms_set'][0]);
    }
}

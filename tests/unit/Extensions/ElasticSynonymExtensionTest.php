<?php

namespace Firesphere\ElasticSearch\Tests\unit\Extensions;

use Elastic\Elasticsearch\Client;
use Firesphere\ElasticSearch\Extensions\ElasticSynonymExtension;
use Firesphere\ElasticSearch\Models\SynonymSet;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\ElasticSearch\Tasks\ElasticConfigureSynonymsTask;
use Firesphere\SearchBackend\Models\SearchSynonym;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class ElasticSynonymExtensionTest extends SapphireTest
{

    public function testOnAfterWrite()
    {
        (new SynonymSet())->requireDefaultRecords();
        $request = new HTTPRequest('GET', 'dev/tasks/ElasticSynonymTask');
        $task = new ElasticConfigureSynonymsTask();

        $task->run($request);

        SynonymSet::singleton()->requireDefaultRecords();
        /** @var SynonymSet $set */
        $set = SynonymSet::get()->first();
        /** @var SearchSynonym $synonym */
        $synonym = SearchSynonym::create(['Keyword' => 'Simon', 'Synonym' => 'Firesphere']);
        $extension = new ElasticSynonymExtension();
        $extension->setOwner($synonym);

        $synonym->write();

        /** @var Client $client */
        $client = Injector::inst()->get(ElasticCoreService::class)->getClient();
        $synonymCheck = $client->synonyms()->getSynonymRule([
            'set_id'  => $set->Key,
            'rule_id' => $synonym->getModifiedID()
        ]);

        $check = $synonymCheck->asArray();

        $this->assertEquals(['id' => $set->Key, 'synonyms' => $synonym->getCombinedSynonym()], $check);

        $synonym->delete();

        $synonymCheck = $client->synonyms()->getSynonymRule([
            'set_id'  => $set->Key,
            'rule_id' => $synonym->getModifiedID()
        ]);

        $this->assertEquals(404, $synonymCheck->getStatusCode());

    }
}

<?php

namespace Firesphere\ElasticSearch\Tests;

use App\src\SearchIndex;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\ElasticSearch\Results\SearchResult;
use Firesphere\ElasticSearch\Tasks\ElasticIndexTask;
use GuzzleHttp\Psr7\Response;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use TestObject;
use TestPage;
use TestRelationObject;

class SearchResultTest extends SapphireTest
{
    protected $usesDatabase = true;
    protected function setUp(): void
    {
        $testPage = new TestPage(['Title' => 'Test page']);
        $testObj = new TestObject(['Title' => 'TestObject']);
        $testObj->write();
        $testObject = new TestRelationObject(['Title' => 'TestRelationObject']);
        $testObject->write();
        $testPage->TestObjectID = $testObj->ID;
        $testPage->write();
        $testPage->RelationObject()->add($testObject);
        $testPage->publishRecursive();
        parent::setUp();
    }

    public function testCreateViewableData()
    {
        $requestResponse = new Response(
            200,
            [
                Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME,
                'Content-Type'              => 'application/json'
            ],
            file_get_contents(__DIR__ . '/../../Fixtures/elasticresponse.json')
        );

        $response = new Elasticsearch();
        $response->setResponse($requestResponse);

        $result = new SearchResult($response, new ElasticQuery(), new SearchIndex());

        $this->assertInstanceOf(ArrayList::class, $result->getMatches());
        $this->assertInstanceOf(ArrayData::class, $result->getFacets());
        /** @var ArrayList $testFacet */
        $testFacet = $result->getFacets()['TestObject'];
        $this->assertEquals(1, $testFacet->count());
        $this->assertInstanceOf(TestObject::class, $testFacet->first());
    }
}
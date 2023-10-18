<?php

namespace Firesphere\ElasticSearch\Tests;

use App\src\SearchIndex;
use Elastic\Elasticsearch\Client;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class ElasticIndexTest extends SapphireTest
{
    /**
     * @var ElasticIndex
     */
    private $index;
    /**
     * @var array
     */
    private $indexConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->index = new SearchIndex();
        $this->indexConfig = Config::inst()->get(ElasticIndex::class, $this->index->getIndexName());
    }

    public function testConstruct()
    {
        $this->assertInstanceOf(Client::class, $this->index->getClient());
    }

    public function testInit()
    {
        $index = $this->index;
        $index->init();

        $classes = $index->getClasses();

        $this->assertEquals($this->indexConfig['Classes'], $classes);
    }

    public function testAddSetGet()
    {
        $index = $this->index;

        $index->addClass(\PageController::class);
        // Add/set/get classes
        $conf = $this->indexConfig;
        $conf['Classes'][] = \PageController::class;
        $this->assertEquals($conf['Classes'], $index->getClasses());
        $index->setClasses($this->indexConfig['Classes']);
        $this->assertEquals($this->indexConfig['Classes'], $index->getClasses());

        // Add/set/get Fulltextfields
        $conf = $this->indexConfig;
        $conf['FulltextFields'] = array_merge(
            $conf['FulltextFields'] ?? [],
        );
        $this->assertEquals($conf['FulltextFields'], $index->getFulltextFields());
        $index->addFulltextField('Dummyfield');
        $conf['FulltextFields'][] = 'Dummyfield';
        $this->assertEquals($conf['FulltextFields'], $index->getFulltextFields());
        $index->setFulltextFields($this->indexConfig['FulltextFields']);
        $this->assertEquals($this->indexConfig['FulltextFields'], $index->getFulltextFields());

        // Add/set/get SortFields
        $this->assertEquals([], $index->getSortFields());
        $index->addSortField('Blub');
        $this->assertContains('Blub', $index->getFulltextFields());
        $this->assertEquals(['Blub'], $index->getSortFields());
        $index->setSortFields(['Blub', 'Blubblub']);
        $this->assertContains('Blubblub', $index->getFullTextFields());

        $index->setFilterFields(['FieldA', 'FieldB']);

        $this->assertContains('FieldA', $index->getFilterFields());
        $this->assertContains('FieldA', $index->getFulltextFields());
        $index->addFilterField('FieldC');
        $this->assertContains('FieldC', $index->getFilterFields());
        $this->assertContains('FieldC', $index->getFulltextFields());


        $expectedFacets = [
            'TestObject' => [
                'BaseClass' => 'Page',
                'Field'     => 'TestObject.Title',
                'Title'     => 'TestObject',
            ]
        ];
        $this->assertEquals($expectedFacets, $index->getFacetFields());

        $index->setFacetFields([\Page::class => ['Field' => 'MyContent']]);
        $this->assertEquals([\Page::class => ['Field' => 'MyContent']], $index->getFacetFields());
        $this->assertContains('MyContent', $index->getFulltextFields());
        $index->addFacetField('Field1', ['Field' => 'Field1']);
        $this->assertArrayHasKey('Field1', $index->getFacetFields());

        $index->setStoredFields(['Title', 'Content', 'FieldD']);
        $this->assertContains('FieldD', $index->getFulltextFields());
        $this->assertEquals(['Title', 'Content', 'FieldD'], $index->getStoredFields());
    }

    public function testAddAllFields()
    {
        $index = $this->index;

        $index->addAllFulltextFields();

        $this->assertIsArray($index->getFulltextFields());
        $array = $index->getFulltextFields();
        $index->addAllDateFields();
        $this->assertNotEquals($array, $index->getFulltextFields());
    }
}

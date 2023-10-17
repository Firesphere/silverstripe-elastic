<?php

namespace Firesphere\ElasticSearch\Tests;

use Elastic\Elasticsearch\Client;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use App\src\SearchIndex;

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
            $conf['FilterFields'] ?? [],
            $conf['SortFields'] ?? []
        );
        $conf['FacetFields'] = $conf['FacetFields'] ?? [];
        foreach ($conf['FacetFields'] as $field => $options) {
            $conf['FulltextFields'][] = $options['Field'];
        }
        $this->assertEquals($conf['FulltextFields'], $index->getFulltextFields());
        $index->addFulltextField('Dummyfield');
        $conf['FulltextFields'][] = 'Dummyfield';
        $this->assertEquals($conf['FulltextFields'], $index->getFulltextFields());
        $index->setFulltextFields($this->indexConfig['FulltextFields']);
        $this->assertEquals($this->indexConfig['FulltextFields'], $index->getFulltextFields());

        // Add/set/get SortFields
        $conf = $this->indexConfig;
        $this->assertEquals([], $index->getSortFields());
        $index->addSortField('Blub');
        $this->assertContains('Blub', $index->getFulltextFields());
        $this->assertEquals([], $index->getSortFields());

        $this->assertEquals($this->indexConfig['FacetFields'], $index->getFacetFields());
    }

    public function testAddAllFields()
    {
        $index = $this->index;

        $index->addAllFulltextFields();

        $this->assertIsArray($index->getFulltextFields());
    }
}

<?php

namespace Firesphere\ElasticSearch\Queries;

use App\src\SearchIndex;
use Firesphere\ElasticSearch\Queries\Builders\QueryBuilder;
use SilverStripe\Dev\SapphireTest;

/**
 * @package Firesphere\Elastic\Tests
 */
class QueryBuilderTest extends SapphireTest
{
    protected static $expected_query = [
        'index' => 'search-testindex',
        'from'  => 0,
        'size'  => 20,
        'body'  => [
            'query'   => [
                'bool' => [
                    'must'   => [
                        [
                            'match' => [
                                '_text' => 'TestSearch'
                            ]
                        ]
                    ],
                    'filter' => [
                        'bool' => [
                            'must'   => [
                                [
                                    'terms' => [
                                        'ViewStatus' => [
                                            "null",
                                            'LoggedIn'
                                        ],
                                    ]
                                ],
                                [
                                    'terms' => [
                                        'SiteTree.Title' => [
                                            'Home'
                                        ]
                                    ]
                                ]
                            ],
                            'should' => [
                                [
                                    'terms' => [
                                        'SiteTree.Title' => [
                                            'Away'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'suggest' => [
                '0-fullterm' => [
                    'text' => 'TestSearch',
                    'term' => [
                        'field' => '_text'
                    ]
                ]
            ],
            'aggs'    => [
                'TestObject' => [
                    'terms' => [
                        'field' => 'Page.TestObject.ID'
                    ]
                ]
            ]
        ]
    ];

    public function testBuildQuery()
    {
        $query = new ElasticQuery();
        $idx = new SearchIndex();
        $query->addTerm('TestSearch');
        $query->addFilter('SiteTree.Title', 'Home');
        $query->addOrFilters('SiteTree.Title', 'Away');
        $query->setHighlight(false);

        $this->assertEquals('Home', $query->getFilters()['SiteTree.Title']);
        $this->assertEquals('Away', $query->getOrFilters()['SiteTree.Title']);
        $this->assertEquals([['text' => 'TestSearch', 'fields' => [], 'boost' => 1]], $query->getTerms());

        $resultQuery = QueryBuilder::buildQuery($query, $idx);

        $this->assertEquals(self::$expected_query, $resultQuery);

        $query->addBoostedField('SiteTree.Title', 2);

        $this->assertEquals(['SiteTree.Title' => 2], $query->getBoostedFields());

        $resultQuery = QueryBuilder::buildQuery($query, $idx);

        $expected = self::$expected_query;
        $expected['body']['query']['bool']['should'] = [
            [
                'match' => [
                    'SiteTree.Title' => [
                        'query' => 'TestSearch',
                        'boost' => 2
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $resultQuery);

        $query->setHighlight(true);
        $resultQuery = QueryBuilder::buildQuery($query, $idx);

        $this->assertArrayHasKey('highlight', $resultQuery['body']);

        $query->addTerm('Test Tset');
        $resultQuery = QueryBuilder::buildQuery($query, $idx);

        $this->assertEquals('Test', $resultQuery['body']['suggest']['0-partterm']['text']);
        $this->assertEquals('Tset', $resultQuery['body']['suggest']['1-partterm']['text']);
        $this->assertEquals('Test Tset', $resultQuery['body']['suggest']['1-fullterm']['text']);

        $query->setSort(['Title' => 'asc']);

        $resultQuery = QueryBuilder::buildQuery($query, $idx);

        $this->assertEquals(['Title' => 'asc'], $resultQuery['body']['sort']);
    }
}

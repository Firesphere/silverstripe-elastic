<?php

namespace Firesphere\ElasticSearch\Queries;

use Firesphere\ElasticSearch\Queries\Builders\QueryBuilder;
use SilverStripe\Dev\SapphireTest;
use App\src\SearchIndex;
class QueryBuilderTest extends SapphireTest
{
    protected static $expected_query = [
        'index' => 'search-testindex',
        'from'  => 0,
        'size'  => 10,
        'body'  => [
            'query' => [
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
                                        ]
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
            ]
        ]
    ];

    public function testBuildQuery()
    {
        $query = new ElasticQuery();
        $query->addTerm('TestSearch');
        $query->addFilter('SiteTree.Title', 'Home');
        $query->addOrFilters('SiteTree.Title', 'Away');

        $this->assertEquals('Home', $query->getFilters()['SiteTree.Title']);
        $this->assertEquals('Away', $query->getOrFilters()['SiteTree.Title']);
        $this->assertEquals([['text' => 'TestSearch', 'fields' => []]], $query->getTerms());

        $query = QueryBuilder::buildQuery($query, new SearchIndex());

        $this->assertEquals(self::$expected_query, $query);

    }
}
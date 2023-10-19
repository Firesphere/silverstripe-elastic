<?php

namespace Firesphere\ElasticSearch\Tests;

use Firesphere\ElasticSearch\Queries\ElasticQuery;
use SilverStripe\Dev\SapphireTest;

class ElasticQueryTest extends SapphireTest
{
    public function testTerms()
    {
        $query = new ElasticQuery();
        $query->addTerm('Testing', [], 1);

        $this->assertEquals([
            [
                'text'   => 'Testing',
                'fields' => [],
                'boost'  => 1,
                'fuzzy'  => null
            ]
        ], $query->getTerms());

        $query->addTerm('Test 2', ['SiteTree.Title'], 2);

        $this->assertEquals([
            [
                'text'   => 'Testing',
                'fields' => [],
                'boost'  => 1,
                'fuzzy'  => null
            ],
            [
                'text'   => 'Test 2',
                'fields' => ['SiteTree.Title'],
                'boost'  => 2,
                'fuzzy'  => null
            ]
        ], $query->getTerms());

        $query->addTerm('Fuzzy test', [], 1, true);


        $this->assertEquals([
            [
                'text'   => 'Testing',
                'fields' => [],
                'boost'  => 1,
                'fuzzy'  => null
            ],
            [
                'text'   => 'Test 2',
                'fields' => ['SiteTree.Title'],
                'boost'  => 2,
                'fuzzy'  => null
            ],
            [
                'text'   => 'Fuzzy test',
                'fields' => [],
                'boost'  => 1,
                'fuzzy'  => true
            ]
        ], $query->getTerms());
    }
}

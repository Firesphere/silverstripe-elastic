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
                'boost'  => 1
            ]
        ], $query->getTerms());

        $query->addTerm('Test 2', ['SiteTree.Title'], 2);

        $this->assertEquals([
            [
                'text'   => 'Testing',
                'fields' => [],
                'boost'  => 1
            ],
            [
                'text'   => 'Test 2',
                'fields' => ['SiteTree.Title'],
                'boost'  => 2
            ]
        ], $query->getTerms());
    }
}

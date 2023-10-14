<?php

namespace Firesphere\ElasticSearch\Tests;

use Firesphere\ElasticSearch\Models\SynonymSet;
use SilverStripe\Dev\SapphireTest;

class SynonymSetTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRequireDefaultRecords()
    {
        (new SynonymSet())->requireDefaultRecords();
        $this->assertEquals(1, SynonymSet::get()->count(), 'There can be only one');
        $key = SynonymSet::get()->first()->Key;
        $this->assertNotNull(SynonymSet::get()->first()->Key);
        (new SynonymSet())->requireDefaultRecords();
        $this->assertEquals(1, SynonymSet::get()->count(), 'There can be only one');
        $this->assertEquals($key, SynonymSet::get()->first()->Key);
    }
}

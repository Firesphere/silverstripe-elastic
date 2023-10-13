<?php

namespace Firesphere\ElasticSearch\Tests;

use Firesphere\ElasticSearch\Models\SynonymSet;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DatabaseAdmin;

class SynonymSetTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRequireDefaultRecords()
    {
        $request = new HTTPRequest('GET', 'dev/build', ['quiet' => true, 'flush' => 1]);
        DatabaseAdmin::singleton()->setRequest($request)->build();

        $this->assertEquals(1, SynonymSet::get()->count(), 'There can be only one');
        $this->assertNotNull(SynonymSet::get()->first()->Key);
    }
}

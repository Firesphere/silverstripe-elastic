<?php
/**
 * class SynonymSet|Firesphere\ElasticSearch\Models\SynonymSet Index items from the CMS through a QueuedJob
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */
namespace Firesphere\ElasticSearch\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\UniqueKey\UniqueKeyService;

/**
 * Class \Firesphere\ElasticSearch\Models\SynonymSet
 *
 * @property string $Name
 * @property string $Key
 */
class SynonymSet extends DataObject
{
    private static $table_name = 'SynonymSet';

    private static $db = [
        'Name' => DBVarchar::class,
        'Key'  => DBVarchar::class
    ];

    public function requireDefaultRecords()
    {
        if (!self::get()->count()) {
            self::create([
                'Name' => 'Default',
                'Key'  => UniqueKeyService::singleton()->generateKey($this)
            ])->write();
        }
        parent::requireDefaultRecords();
    }
}

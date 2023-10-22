<?php
/**
 * class ElasticSynonymExtension|Firesphere\ElasticSearch\Extensions\ElasticSynonymExtension Synonym updates for Elastic
 * pushed to Elastic
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Extensions;

use Firesphere\ElasticSearch\Models\SynonymSet;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Models\SearchSynonym;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;

/**
 * Class \Firesphere\ElasticSearch\Extensions\ElasticSynonymExtension
 *
 * @property SearchSynonym|ElasticSynonymExtension $owner
 */
class ElasticSynonymExtension extends DataExtension
{
    /**
     * Add or update this synonym in Elastic
     *
     * @throws NotFoundExceptionInterface
     */
    public function onAfterWrite()
    {
        $service = Injector::inst()->get(ElasticCoreService::class);
        /** @var SearchSynonym|ElasticSynonymExtension $owner */
        $owner = $this->owner;
        $syn = $service->getClient()->synonyms();
        /** @var SynonymSet $set */
        $set = SynonymSet::get()->first();
        $syn->putSynonymRule([
            'set_id'  => $set->Key,
            'rule_id' => $owner->getModifiedID(),
            'body'    => [
                'synonyms' => $owner->getCombinedSynonym()
            ]
        ]);
    }

    /**
     * When deleting a synonym from the CMS, delete it as a rule
     *
     * @throws NotFoundExceptionInterface
     */
    public function onAfterDelete()
    {
        $service = Injector::inst()->get(ElasticCoreService::class);
        $syn = $service->getClient()->synonyms();
        /** @var SearchSynonym $owner */
        $owner = $this->owner;
        /** @var SynonymSet $set */
        $set = SynonymSet::get()->first();
        $syn->deleteSynonymRule(['set_id' => $set->Key, 'rule_id' => $owner->getModifiedId()]);
        parent::onAfterDelete();
    }
}

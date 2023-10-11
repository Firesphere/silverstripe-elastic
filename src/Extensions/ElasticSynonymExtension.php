<?php

namespace Firesphere\ElasticSearch\Extensions;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Firesphere\ElasticSearch\Models\SynonymSet;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Models\SearchSynonym;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Class \Firesphere\ElasticSearch\Extensions\ElasticSynonymExtension
 *
 * @property SearchSynonym|ElasticSynonymExtension $owner
 */
class ElasticSynonymExtension extends DataExtension
{
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('SynonymSets');
        parent::updateCMSFields($fields);
    }

    /**
     * Add or update this synonym in Elastic
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function onAfterWrite()
    {
        $syn = (new ElasticCoreService())->getClient()->synonyms();
        /** @var SynonymSet $set */
        $set = SynonymSet::get()->first();
        $syn->putSynonymRule([
            'set_id'  => $set->Key,
            'rule_id' => $this->owner->getModifiedID(),
            'body'    => [
                'synonyms' => $this->owner->getCombinedSynonym()
            ]
        ]);
    }

    /**
     * When deleting a synonym from the CMS, delete it as a rule
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function onAfterDelete()
    {
        $syn = (new ElasticCoreService())->getClient()->synonyms();
        /** @var SynonymSet $set */
        $set = SynonymSet::get()->first();
        $syn->deleteSynonymRule(['set_id' => $set->Key, 'rule_id' => $this->owner->getModifiedId()]);
        parent::onAfterDelete();
    }
}

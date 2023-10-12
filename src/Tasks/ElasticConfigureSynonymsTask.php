<?php

namespace Firesphere\ElasticSearch\Tasks;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Firesphere\ElasticSearch\Models\SynonymSet;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Helpers\Synonyms as BaseSynonyms;
use Firesphere\SearchBackend\Models\SearchSynonym;
use SilverStripe\Dev\BuildTask;

class ElasticConfigureSynonymsTask extends BuildTask
{

    /**
     * URLSegment of this task
     *
     * @var string
     */
    private static $segment = 'ElasticSynonymTask';
    /**
     * My name
     *
     * @var string
     */
    protected $title = 'Add/update synonyms in Elasticsearch';
    /**
     * What do I do?
     *
     * @var string
     */
    protected $description = 'Add or update synonyms to Elastic.';

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function run($request)
    {
        $client = (new ElasticCoreService())->getClient();
        $baseSynonyms = BaseSynonyms::getSynonymsFlattened();
        $baseSynonyms = $this->transformBaseSynonyms($baseSynonyms);
        $configuredSynonyms = SearchSynonym::get()->map('getModifiedID', 'getCombinedSynonym')->toArray();
        $configuredSynonyms = $this->transformBaseSynonyms($configuredSynonyms, '');
        // Note, the Elastic synonym class is not suitable for a bulk import
        $client->synonyms()->putSynonym([
            'id'   => SynonymSet::get()->first()->Key,
            'body' => ["synonyms_set" => array_merge($baseSynonyms, $configuredSynonyms)]
        ]);
    }

    private function transformBaseSynonyms(array $synonyms, string $prefix = 'base')
    {
        $return = [];
        foreach ($synonyms as $key => $synonym) {
            if (strlen($prefix) > 0) {
                $key = sprintf("%s-%s", $prefix, $key);
            }
            $return[] = ["id" => $key, "synonyms" => $synonym];
        }

        return $return;
    }
}

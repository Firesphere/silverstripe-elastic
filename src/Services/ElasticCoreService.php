<?php

namespace Firesphere\ElasticSearch\Services;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\ElasticSearch\Client;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Firesphere\ElasticSearch\Indexes\BaseIndex;
use Firesphere\SearchBackend\Services\BaseService;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;

class ElasticCoreService extends BaseService
{
    use Configurable;

    /**
     * @var Client Comms client with Elastic
     */
    protected $client;

    /**
     * @throws \ReflectionException
     * @throws AuthenticationException
     */
    public function __construct()
    {
        $config = self::config()->get('config');
        $this->client = ClientBuilder::create()
            ->setHosts([$config['endpoint'][0]['host']])
            ->setApiKey($config['endpoint'][0]['apiKey'])
            ->build();
//        $this->client = new Client($config['endpoint'][0]);
        parent::__construct(BaseIndex::class);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }
}

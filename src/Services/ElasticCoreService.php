<?php

namespace Firesphere\ElasticSearch\Services;

use Elastic\ElasticSearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Firesphere\ElasticSearch\Indexes\BaseIndex;
use Firesphere\SearchBackend\Services\BaseService;
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
        $endPoint0 = $config['endpoint'][0];
        $uri = str_replace(['https://', 'http://'], '', $endPoint0['host']);
        $uri = sprintf(
            '%s://%s:%s',
            $endPoint0['protocol'] ?? 'https',
            $uri,
            $endPoint0['port'] ?? 9200
        );
        $this->client = ClientBuilder::create()
            ->setHosts([$uri])
            ->setApiKey($endPoint0['apiKey'])
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

    public function updateIndex($index, $items)
    {
        // @todo get the document factory in play here
    }
}

<?php

namespace Firesphere\ElasticSearch\Services;

use Elastic\ElasticSearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Firesphere\ElasticSearch\Factories\DocumentFactory;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\SearchBackend\Services\BaseService;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\SS_List;

class ElasticCoreService extends BaseService
{
    use Configurable;

    private const ENVIRONMENT_VARS = [
        'ELASTIC_ENDPOINT' => 'host',
        'ELASTIC_USERNAME' => 'username',
        'ELASTIC_PASSWORD' => 'password',
        'ELASTIC_API_KEY'  => 'apiKey',
        'ELASTIC_PORT'     => 'port',
        'ELASTIC_PROTOCOL' => 'protocol'
    ];

    /**
     * @var Client Comms client with Elastic
     */
    protected $client;

    /**
     * @throws ReflectionException
     * @throws AuthenticationException
     */
    public function __construct()
    {
        $config = self::config()->get('config');
        if ($config['endpoint'] === 'ENVIRONMENT') {
            $endpoint0 = [];
            foreach (self::ENVIRONMENT_VARS as $envVar => $elasticVar) {
                $endpoint0[$elasticVar] = Environment::getEnv($envVar);
            }
        } else {
            $endpoint0 = $config['endpoint'][0];
        }
        $uri = str_replace(['https://', 'http://'], '', $endpoint0['host']);
        $uri = sprintf(
            '%s://%s:%s',
            $endpoint0['protocol']?: 'https',
            $uri,
            $endpoint0['port']?: 9200
        );
        $builder = ClientBuilder::create()
            ->setHosts([$uri]);
        if ($endpoint0['apiKey']) {
            $builder->setApiKey($endpoint0['apiKey']);
        } elseif ($endpoint0['username'] && $endpoint0['password']) {
            $builder->setBasicAuthentication($endpoint0['username'], $endpoint0['password']);
        }
        $this->client = $builder->build();
        parent::__construct(ElasticIndex::class);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * @param ElasticIndex $index
     * @param SS_List $items
     * @throws NotFoundExceptionInterface
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function updateIndex($index, $items)
    {
        $fields = $index->getFieldsForIndexing();
        $factory = $this->getFactory($items);
        $docs = $factory->buildItems($fields, $index);
        if (count($docs)) {
            $body = [
                'pipeline' => 'ent-search-generic-ingestion',
                'body'     => []
            ];
            foreach ($docs as $doc) {
                $body['body'][] = [
                    'index' => [
                        '_index' => $index->getIndexName(),
                    ]
                ];
                $doc['_extract_binary_content'] = true;
                $doc['_reduce_whitespace'] = true;
                $doc['_run_ml_inference'] = false;
                $body['body'][] = $doc;
            }
            $this->client->bulk($body);
        }
    }


    /**
     * Get the document factory prepared
     *
     * @param SS_List $items
     * @return DocumentFactory
     * @throws NotFoundExceptionInterface
     */
    protected function getFactory($items): DocumentFactory
    {
        $factory = Injector::inst()->get(DocumentFactory::class);
        $factory->setItems($items);
        $factory->setClass($items->first()->ClassName);
        $factory->setDebug(true);

        return $factory;
    }
}

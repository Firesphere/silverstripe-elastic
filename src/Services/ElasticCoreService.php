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

    public const ID_KEY = 'UniqueKey';

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
        $endpoint0 = $this->getEndpointConfig();
        $uri = str_replace(['https://', 'http://'], '', $endpoint0['host']);
        $uri = sprintf(
            '%s://%s:%s',
            $endpoint0['protocol'],
            $uri,
            $endpoint0['port'] ?: 9200
        );
        $builder = $this->getBuilder($uri, $endpoint0);
        $this->client = $builder->build();
        parent::__construct(ElasticIndex::class);
    }

    /**
     * @return array
     */
    private function getEndpointConfig(): array
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
        // default to https
        $endpoint0['protocol'] = $endpoint0['protocol'] ?? 'https';

        return $endpoint0;
    }

    /**
     * @param string $uri
     * @param array $endpoint0
     * @return ClientBuilder
     */
    private function getBuilder(string $uri, array $endpoint0): ClientBuilder
    {
        $builder = ClientBuilder::create()
            ->setHosts([$uri]);
        if ($endpoint0['apiKey']) {
            $builder->setApiKey($endpoint0['apiKey']);
        } elseif ($endpoint0['username'] && $endpoint0['password']) {
            $builder->setBasicAuthentication($endpoint0['username'], $endpoint0['password']);
        }
        // Disable the SSL Certificate check
        if (Environment::getEnv('ELASTIC_DISABLE_SSLCHECK')) {
            $builder->setSSLVerification(false);
        }

        return $builder;
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
     * @return void|array
     * @throws NotFoundExceptionInterface
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function updateIndex($index, $items, $returnDocs = false)
    {
        $fields = $index->getFieldsForIndexing();
        $factory = $this->getFactory($items);
        $docs = $factory->buildItems($fields, $index);
        $body = ['body' => []];
        if (count($docs)) {
            $body = $this->buildBody($docs, $index);
            $this->client->bulk($body);
        }
        if ($returnDocs) {
            return $body['body'];
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

    /**
     * @param array $docs
     * @param ElasticIndex $index
     * @return array
     */
    public function buildBody(array $docs, ElasticIndex $index): array
    {
        $body = ['body' => []];
        if (self::config()->get('pipeline')) {
            $body['body'] = [ // @todo Check if this is indeed how it works
                              'index'    => $index->getIndexName(),
                              'pipeline' => self::config()->get('pipeline')
            ];
        }
        foreach ($docs as $doc) {
            $body['body'][] = [
                'index' => [
                    '_index' => $index->getIndexName(),
                    '_id'    => $doc[self::ID_KEY]
                ]
            ];
            $doc['_extract_binary_content'] = true;
            $doc['_reduce_whitespace'] = true;
            $doc['_run_ml_inference'] = false;
            $body['body'][] = $doc;
        }

        return $body;
    }
}

<?php

namespace Firesphere\ElasticSearch\Services;

use Elastic\ElasticSearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Firesphere\ElasticSearch\Indexes\BaseIndex;
use Firesphere\SearchBackend\Factories\DocumentFactory;
use Firesphere\SearchBackend\Services\BaseService;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;

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

    /**
     * @throws NotFoundExceptionInterface
     */
    public function updateIndex($index, $items, $update)
    {
        $fields = $index->getFieldsForIndexing();
        $factory = $this->getFactory($items);
        $docs = $factory->buildItems($fields, $index, $update);
        if (count($docs)) {
            $update->addDocuments($docs);
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

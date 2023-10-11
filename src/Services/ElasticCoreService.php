<?php

namespace Firesphere\ElasticSearch\Services;

use Elastic\ElasticSearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Firesphere\ElasticSearch\Factories\DocumentFactory;
use Firesphere\ElasticSearch\Indexes\BaseIndex;
use Firesphere\SearchBackend\Services\BaseService;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\SS_List;

class ElasticCoreService extends BaseService
{
    use Configurable;

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
        $endPoint0 = $config['endpoint'][0];
        $uri = str_replace(['https://', 'http://'], '', $endPoint0['host']);
        $uri = sprintf(
            '%s://%s:%s',
            $endPoint0['protocol'] ?? 'https',
            $uri,
            $endPoint0['port'] ?? 9200
        );
        $builder = ClientBuilder::create()
            ->setHosts([$uri]);
        if ($endPoint0['apiKey']) {
            $builder->setApiKey($endPoint0['apiKey']);
        } elseif ($endPoint0['username'] && $endPoint0['password']) {
            $builder->setBasicAuthentication($endPoint0['username'], $endPoint0['password']);
        }
        $this->client = $builder->build();
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
     * @param BaseIndex $index
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

$params = [
    'pipeline' => 'ent-search-generic-ingestion',
    'body'     => [
        [
            'index' => [
                '_index' => 'search-stickerindex',
                '_id'    => '9780553351927',
            ],
        ],
        [
            'name'                    => 'Snow Crash',
            'author'                  => 'Neal Stephenson',
            'release_date'            => '1992-06-01',
            'page_count'              => 470,
            '_extract_binary_content' => true,
            '_reduce_whitespace'      => true,
            '_run_ml_inference'       => false,
        ],
        [
            'index' => [
                '_index' => 'search-stickerindex',
                '_id'    => '9780441017225',
            ],
        ],
        [
            'name'                    => 'Revelation Space',
            'author'                  => 'Alastair Reynolds',
            'release_date'            => '2000-03-15',
            'page_count'              => 585,
            '_extract_binary_content' => true,
            '_reduce_whitespace'      => true,
            '_run_ml_inference'       => false,
        ],
        [
            'index' => [
                '_index' => 'search-stickerindex',
                '_id'    => '9780451524935',
            ],
        ],
        [
            'name'                    => '1984',
            'author'                  => 'George Orwell',
            'release_date'            => '1985-06-01',
            'page_count'              => 328,
            '_extract_binary_content' => true,
            '_reduce_whitespace'      => true,
            '_run_ml_inference'       => false,
        ],
        [
            'index' => [
                '_index' => 'search-stickerindex',
                '_id'    => '9781451673319',
            ],
        ],
        [
            'name'                    => 'Fahrenheit 451',
            'author'                  => 'Ray Bradbury',
            'release_date'            => '1953-10-15',
            'page_count'              => 227,
            '_extract_binary_content' => true,
            '_reduce_whitespace'      => true,
            '_run_ml_inference'       => false,
        ],
        [
            'index' => [
                '_index' => 'search-stickerindex',
                '_id'    => '9780060850524',
            ],
        ],
        [
            'name'                    => 'Brave New World',
            'author'                  => 'Aldous Huxley',
            'release_date'            => '1932-06-01',
            'page_count'              => 268,
            '_extract_binary_content' => true,
            '_reduce_whitespace'      => true,
            '_run_ml_inference'       => false,
        ],
        [
            'index' => [
                '_index' => 'search-stickerindex',
                '_id'    => '9780385490818',
            ],
        ],
        [
            'name'                    => 'The Handmaid\'s Tale',
            'author'                  => 'Margaret Atwood',
            'release_date'            => '1985-06-01',
            'page_count'              => 311,
            '_extract_binary_content' => true,
            '_reduce_whitespace'      => true,
            '_run_ml_inference'       => false,
        ],
    ],
];

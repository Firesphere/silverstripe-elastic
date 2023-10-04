<?php

namespace Firesphere\ElasticSearch\Services;

use GuzzleHttp\Client;
use SilverStripe\Core\Config\Configurable;

class ElasticCoreService
{
    use Configurable;

    protected $client;

    public function __construct()
    {
        $config = static::config()->get('config');
        $config['endpoint'][0]['host'] = sprintf('%s:%s', $config['endpoint'][0]['host'], $config['endpoint'][0]['port']);

        $client = new \Elastic\EnterpriseSearch\Client($config['endpoint'][0] + ['verify' => false]);
        $this->client = $client;

    }

    public function getClient()
    {
        return $this->client;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }
}

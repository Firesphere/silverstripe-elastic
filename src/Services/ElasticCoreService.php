<?php

namespace Firesphere\ElasticSearch\Services;

use Elastic\EnterpriseSearch\Client;
use SilverStripe\Core\Config\Configurable;

class ElasticCoreService
{
    use Configurable;

    protected $client;

    public function __construct()
    {
        $config = static::config()->get('config');
        $config['endpoint'][0]['host'] = sprintf('%s:%s', $config['endpoint'][0]['host'], $config['endpoint'][0]['port']);

        $this->client = new Client($config['endpoint'][0]);

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

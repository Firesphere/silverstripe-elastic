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

        $this->client = new Client($config['endpoint'][0]);
    }
}

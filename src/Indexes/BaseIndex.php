<?php

namespace Firesphere\ElasticSearch\Indexes;

use Firesphere\ElasticSearch\Services\ElasticCoreService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

abstract class BaseIndex
{
    use Extensible;
    use Configurable;
    use Injectable;

    /**
     * @var \Elastic\EnterpriseSearch\Client Comms client
     */
    protected $client;

    public function __construct()
    {
        $config = Config::inst()->get(ElasticCoreService::class, 'config');
        $this->client = (new ElasticCoreService())->getClient();
    }

    abstract public function getIndexName();
}

<?php
/**
 * class SearchLogger|Firesphere\ElasticSearch\Helpers\SearchLogger Log errors to the Database
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\Elasticsearch\Helpers;

use Countable;
use Elastic\EnterpriseSearch\Client;
use Firesphere\SearchBackend\Helpers\SearchLogger;
use Firesphere\SearchBackend\Models\SearchLog;
use HttpException;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ValidationException;

/**
 * Class SearchLogger
 *
 * Log information from Elastic to the CMS for reference
 *
 * @package Firesphere\Elastic\Search
 */
class ElasticLogger extends SearchLogger
{

    public function __construct()
    {
    }
}

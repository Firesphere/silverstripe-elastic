<?php
/**
 * class SearchLogger|Firesphere\ElasticSearch\Helpers\SearchLogger Log errors to the Database
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Helpers;

use Firesphere\SearchBackend\Helpers\SearchLogger;

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

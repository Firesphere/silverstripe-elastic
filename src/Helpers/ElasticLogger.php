<?php
/**
 * class SearchLogger|Firesphere\SolrSearch\Helpers\SearchLogger Log errors to the Database
 *
 * @package Firesphere\Solr\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\SolrSearch\Helpers;

use Countable;
use Elastic\EnterpriseSearch\Client;
use Firesphere\ElasticSearch\Helpers\SearchLogger;
use Firesphere\SearchBackend\Models\SearchLog;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ValidationException;

/**
 * Class SearchLogger
 *
 * Log information from Solr to the CMS for reference
 *
 * @package Firesphere\Solr\Search
 */
class ElasticLogger extends SearchLogger
{

    /**
     * SearchLogger constructor.
     *
     * @param null|Countable $handler
     */
    public function __construct($handler = null)
    {

        if (isset($hostConfig['username']) && isset($hostConfig['password'])) {
            $this->options = [
                'auth' => [
                    $hostConfig['username'],
                    $hostConfig['password']
                ]
            ];
        }


        $this->client = new Client([]);
    }

    /**
     * Log the given message and dump it out.
     * Also boot the Log to get the latest errors from Solr
     *
     * @param string $type
     * @param string $message
     * @throws HTTPException
     * @throws ValidationException
     */
    public static function logMessage($type, $message): void
    {
        parent::logMessage($type, $message['']);
        $elasticLogger = new self();
        $elasticLogger->saveLog($type);
        /** @var SearchLog $lastError */
        $lastError = SearchLog::get()->last();

        $err = ($lastError === null) ? 'Unknown' : $lastError->getLastErrorLine();
        $errTime = ($lastError === null) ? 'Unknown' : $lastError->Timestamp;
        $message .= sprintf('%sLast known Solr error:%s%s: %s', PHP_EOL, PHP_EOL, $errTime, $err);
        /** @var LoggerInterface $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logger->alert($message);
        if (Director::is_cli() || Controller::curr()->getRequest()->getVar('unittest')) {
            Debug::dump($message);
        }
    }

    /**
     * Save the latest Solr errors to the log
     *
     * @param string $type
     * @throws HTTPException
     * @throws ValidationException
     */
    public function saveElasticLog($type = 'Query'): void
    {
        parent::saveLog($type, []);
    }

    /**
     * Return the Guzzle Client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Set the Guzzle client
     *
     * @param Client $client
     * @return ElasticLogger
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get the options for Guzzle
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set custom options for Guzzle
     *
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}

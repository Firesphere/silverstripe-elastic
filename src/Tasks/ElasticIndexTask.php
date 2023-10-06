<?php

namespace Firesphere\ElasticSearch\Tasks;

use Firesphere\ElasticSearch\Indexes\BaseIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;

class ElasticIndexTask extends BuildTask
{
    /**
     * URLSegment of this task
     *
     * @var string
     */
    private static $segment = 'ElasticIndexTask';
    /**
     * Store the current states for all instances of SiteState
     *
     * @var array
     */
    public $currentStates;
    /**
     * My name
     *
     * @var string
     */
    protected $title = 'Solr Index update';
    /**
     * What do I do?
     *
     * @var string
     */
    protected $description = 'Add or update documents to an existing Solr core.';

    /**
     * @var ElasticCoreService
     */
    protected $service;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Bool
     */
    protected $debug;

    /**
     * @var BaseIndex
     */
    protected $index;

    /**
     * SolrIndexTask constructor. Sets up the document factory
     *
     * @throws NotFoundExceptionInterface
     */
    public function __construct()
    {
        parent::__construct();
        // Only index live items.
        // The old FTS module also indexed Draft items. This is unnecessary
        // If versioned is needed, a separate Versioned Search module is required
        Versioned::set_reading_mode(Versioned::DEFAULT_MODE);
        $this->setService(Injector::inst()->get(ElasticCoreService::class));
        $this->setLogger(Injector::inst()->get(LoggerInterface::class));
    }

    /**
     * @param HTTPRequest $request
     * @return int|void
     * @throws NotFoundExceptionInterface
     */
    public function run($request)
    {
        $start = time();
        $this->getLogger()->info(date('Y-m-d H:i:s'));
        [$vars, $group, $isGroup] = $this->taskSetup($request);
        $groups = 0;
        $indexes = $this->service->getValidIndexes($request->getVar('index'));
        foreach ($indexes as $indexName) {
            /** @var BaseIndex $index */
            $index = Injector::inst()->get($indexName, false);
            $this->setIndex($index);
            $indexClasses = $this->index->getClasses();
            $classes = $this->getClasses($vars, $indexClasses);
            if (!count($classes)) {
                continue;
            }

            // Get the groups
            $groups = $this->indexClassForIndex($classes, $isGroup, $group);

        }
        $this->getLogger()->info(gmdate('Y-m-d H:i:s'));
        $time = gmdate('H:i:s', (time() - $start));
        $this->getLogger()->info(sprintf('Time taken: %s', $time));

        return $groups;
    }

    /**
     * get the classes to run for this task execution
     *
     * @param array $vars URL GET Parameters
     * @param array $classes Classes to index
     * @return array
     */
    protected function getClasses(array $vars, array $classes): array
    {
        if (isset($vars['class'])) {
            return array_intersect($classes, [$vars['class']]);
        }

        return $classes;
    }

    /**
     * Set up the requirements for this task
     *
     * @param HTTPRequest $request Current request
     * @return array
     */
    protected function taskSetup(HTTPRequest $request): array
    {
        $vars = $request->getVars();
        $debug = $this->isDebug() || isset($vars['debug']);
        // Forcefully set the debugging to whatever the outcome of the above is
        $this->setDebug($debug, true);
        $group = $vars['group'] ?? 0;
        $start = $vars['start'] ?? 0;
        $group = ($start > $group) ? $start : $group;
        $isGroup = isset($vars['group']);

        return [$vars, $group, $isGroup];
    }

    /**
     * @return mixed
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param mixed $service
     */
    public function setService($service): void
    {
        $this->service = $service;
    }

    /**
     * @return mixed
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param mixed $logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set the debug mode
     *
     * @param bool $debug Set the task in debug mode
     * @param bool $force Force a task in debug mode, despite e.g. being Live and not CLI
     * @return self
     */
    public function setDebug(bool $debug, bool $force = false): self
    {
        // Make the debug a configurable, forcing it to always be false from config
        if (!$force && ElasticCoreService::config()->get('debug') === false) {
            $debug = false;
        }

        $this->debug = $debug;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @param mixed $index
     */
    public function setIndex($index): void
    {
        $this->index = $index;
    }

}
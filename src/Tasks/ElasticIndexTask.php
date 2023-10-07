<?php

namespace Firesphere\ElasticSearch\Tasks;

use Exception;
use Firesphere\ElasticSearch\Indexes\BaseIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Helpers\IndexingHelper;
use Firesphere\SearchBackend\States\SiteState;
use Firesphere\SearchBackend\Traits\IndexingTraits\IndexingTrait;
use HttpException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

class ElasticIndexTask extends BuildTask
{
    use IndexingTrait;
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
     * @throws HttpException
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
     * Index a single class for a given index. {@link static::indexClassForIndex()}
     *
     * @param bool $isGroup Is a specific group indexed
     * @param string $class Class to index
     * @param int $group Group to index
     * @return int|bool
     * @throws HTTPException
     * @throws ValidationException
     */
    private function indexClass(bool $isGroup, string $class, int $group)
    {
        $this->getLogger()->info(sprintf('Indexing %s for %s', $class, $this->getIndex()->getIndexName()));
        [$totalGroups, $groups] = IndexingHelper::getGroupSettings($isGroup, $class, $group);
        $this->getLogger()->info(sprintf('Total groups %s', $totalGroups));
        do {
            try {
                if ($this->hasPCNTL()) {
                    // @codeCoverageIgnoreStart
                    $group = $this->spawnChildren($class, $group, $groups);
                    // @codeCoverageIgnoreEnd
                } else {
                    $this->doReindex($group, $class);
                }
                $group++;
            } catch (Exception $error) {
                // @codeCoverageIgnoreStart
                $this->logException($this->index->getIndexName(), $group, $error);
                continue;
                // @codeCoverageIgnoreEnd
            }
        } while ($group <= $groups);

        return $totalGroups;
    }


    /**
     * Reindex the given group, for each state
     *
     * @param int $group Group to index
     * @param string $class Class to index
     * @param bool|int $pid Are we a child process or not
     * @throws Exception
     */
    private function doReindex(int $group, string $class, $pid = false)
    {
        $start = time();
        $states = SiteState::getStates();
        foreach ($states as $state) {
            if ($state !== SiteState::DEFAULT_STATE && !empty($state)) {
                SiteState::withState($state);
            }
            $this->indexStateClass($group, $class);
        }

        SiteState::withState(SiteState::DEFAULT_STATE);
        $end = gmdate('i:s', time() - $start);
        $this->getLogger()->info(sprintf('Indexed group %s in %s', $group, $end));

        // @codeCoverageIgnoreStart
        if ($pid !== false) {
            exit(0);
        }
        // @codeCoverageIgnoreEnd
    }


    /**
     * Index a group of a class for a specific state and index
     *
     * @param string $group Group to index
     * @param string $class Class to index
     * @throws Exception
     */
    private function indexStateClass(string $group, string $class): void
    {
        // Generate filtered list of local records
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        /** @var DataList|DataObject[] $items */
        $items = DataObject::get($baseClass)
            ->sort('ID ASC')
            ->limit($this->getBatchLength(), ($group * $this->getBatchLength()));
        if ($items->count()) {
            $this->updateIndex($items);
        }
    }


    /**
     * Execute the update on the client
     *
     * @param SS_List $items Items to index
     * @throws Exception
     */
    private function updateIndex($items): void
    {
        $index = $this->getIndex();
        $this->service->updateIndex($index, $items);
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
     * @return BaseIndex
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
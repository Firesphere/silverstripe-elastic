<?php
/**
 * class DataObjectExtension|Firesphere\ElasticSearch\Extensions\DataObjectExtension Adds checking if changes should be
 * pushed to Elastic
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Extensions;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Exception;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Http\Promise\Promise;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

/**
 * Class \Firesphere\ElasticSearch\Extensions\DataObjectElasticExtension
 *
 * @property DataObject|DataObjectElasticExtension $owner
 */
class DataObjectElasticExtension extends DataExtension
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ValidationException
     */
    public function onAfterDelete()
    {
        parent::onAfterDelete();
        $this->deleteFromElastic();
    }

    /**
     * Can be called directly, if a DataObject needs to be removed
     * immediately.
     * @return void
     * @throws NotFoundExceptionInterface
     */
    public function deleteFromElastic(): void
    {
        $service = new ElasticCoreService();
        $indexes = $service->getValidIndexes();
        foreach ($indexes as $index) {
            /** @var ElasticIndex $idx */
            $idx = Injector::inst()->get($index);
            $config = ElasticIndex::config()->get($idx->getIndexName());
            if (in_array($this->owner->ClassName, $config['Classes'])) {
                $deleteQuery = $this->getDeleteQuery($index);
                $this->executeQuery($service, $deleteQuery);
            }
        }
    }

    /**
     * @param mixed $index
     * @return array
     */
    private function getDeleteQuery(mixed $index): array
    {
        return [
            'index' => $index,
            'body'  => [
                'query' => [
                    'match' => [
                        'id' => sprintf('%s-%s', $this->owner->ClassName, $this->owner->ID)
                    ]
                ]
            ]
        ];
    }

    /**
     * @param ElasticCoreService $service
     * @param array $deleteQuery
     * @return Elasticsearch|Promise|bool
     * @throws NotFoundExceptionInterface
     */
    protected function executeQuery(ElasticCoreService $service, array $deleteQuery)
    {
        try {
            return $service->getClient()->deleteByQuery($deleteQuery);
        } catch (Exception $e) {
            // DirtyClass handling is a DataObject Search Core extension
            $dirty = $this->owner->getDirtyClass('DELETE');
            $ids = json_decode($dirty->IDs ?? '[]');
            $ids[] = $this->owner->ID;
            $dirty->IDs = json_encode($ids);
            $dirty->write();
            /** @var LoggerInterface $logger */
            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error($e->getMessage(), $e->getTrace());

            return false;
        }
    }

    /**
     * Reindex after write, if it's an indexed new/updated object
     * @throws ClientResponseException
     * @throws NotFoundExceptionInterface
     * @throws ServerResponseException
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if (
            !$this->owner->hasExtension(Versioned::class) ||
            ($this->owner->hasExtension(Versioned::class) && $this->owner->isPublished())
        ) {
            $this->pushToElastic();
        }

        // @codeCoverageIgnoreStart Elastic during tests isn't fast enough to pick this up properly
        if ($this->owner->isChanged('ShowInSearch') && !$this->owner->ShowInSearch) {
            $this->deleteFromElastic();
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * This is a separate method from the delete action, as it's a different route
     * and query components.
     * It can be called to add an object to the index immediately, without
     * requiring a write.
     * @return array|void|bool
     * @throws ClientResponseException
     * @throws NotFoundExceptionInterface
     * @throws ServerResponseException
     */
    public function pushToElastic()
    {
        $list = ArrayList::create();
        $list->push($this->owner);
        /** @var ElasticCoreService $service */
        $service = Injector::inst()->get(ElasticCoreService::class);
        foreach ($service->getValidIndexes() as $indexStr) {
            /** @var ElasticIndex $index */
            $index = Injector::inst()->get($indexStr);
            $idxConfig = ElasticIndex::config()->get($index->getIndexName());
            if (in_array($this->owner->ClassName, $idxConfig['Classes'])) {
                $result = $service->updateIndex($index, $list);
            }
        }

        return $result ?? false;
    }
}

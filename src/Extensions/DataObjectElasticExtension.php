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
use Firesphere\SearchBackend\Extensions\DataObjectSearchExtension;
use Http\Promise\Promise;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\CMS\Model\SiteTree;
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
    protected $deletedFromElastic;
    /**
     * @throws NotFoundExceptionInterface
     */
    public function onAfterDelete()
    {
        parent::onAfterDelete();
        $this->deleteFromElastic();
    }

    /**
     * Can be called directly, if a DataObject needs to be removed
     * immediately.
     * @return bool|Elasticsearch|Promise
     * @throws NotFoundExceptionInterface
     */
    public function deleteFromElastic()
    {
        $result = false;
        $service = new ElasticCoreService();
        $indexes = $service->getValidIndexes();
        foreach ($indexes as $index) {
            /** @var ElasticIndex $idx */
            $idx = Injector::inst()->get($index);
            $config = ElasticIndex::config()->get($idx->getIndexName());
            if (in_array($this->owner->ClassName, $config['Classes'])) {
                $deleteQuery = $this->getDeleteQuery($idx);
                $result = $this->executeQuery($service, $deleteQuery);
            }
        }

        return $result;
    }

    /**
     * @param ElasticIndex $index
     * @return array
     */
    private function getDeleteQuery(ElasticIndex $index): array
    {
        return [
            'index' => $index->getIndexName(),
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
            /** @var DataObjectSearchExtension|DataObject $owner */
            $owner = $this->owner;
            // DirtyClass handling is a DataObject Search Core extension
            $dirty = $owner->getDirtyClass('DELETE');
            $ids = json_decode($dirty->IDs ?? '[]');
            $ids[] = $owner->ID;
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
        /** @var DataObject|SiteTree|DataObjectElasticExtension|DataObjectSearchExtension|Versioned $owner */
        $owner = $this->owner;
        if (
            !$owner->hasExtension(Versioned::class) ||
            ($owner->hasExtension(Versioned::class) && $owner->isPublished())
        ) {
            $this->pushToElastic();
        }

        if ($owner->hasField('ShowInSearch') &&
            $owner->isChanged('ShowInSearch') &&
            !$owner->ShowInSearch) {
            $this->deletedFromElastic = $this->deleteFromElastic();
        }
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
        $result = false;
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

        return $result;
    }

    /**
     * @return mixed
     */
    public function getDeletedFromElastic()
    {
        return $this->deletedFromElastic;
    }
}

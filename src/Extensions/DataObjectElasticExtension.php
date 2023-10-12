<?php

namespace Firesphere\ElasticSearch\Extensions;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Extensions\DataObjectSearchExtension;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

/**
 * Class \Firesphere\ElasticSearch\Extensions\DataObjectElasticExtension
 *
 * @property DataObject|DataObjectSearchExtension|DataObjectElasticExtension|Versioned $owner
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
        $service = new ElasticCoreService();
        $indexes = $service->getValidIndexes();
        foreach ($indexes as $index) {
            /** @var ElasticIndex $idx */
            $idx = Injector::inst()->get($index);
            $config = ElasticIndex::config()->get($idx->getIndexName());
            if (in_array($this->owner->ClassName, $config['Classes'])) {
                $deleteQuery = [
                    'index' => $index,
                    'body'  => [
                        'query' => [
                            'match' => [
                                'id' => sprintf('%s-%s', $this->owner->ClassName, $this->owner->ID)
                            ]
                        ]
                    ]
                ];
                try {
                    $service->getClient()->deleteByQuery($deleteQuery);
                } catch (Exception $e) {
                    $dirty = $this->owner->getDirtyClass('DELETE');
                    $ids = json_decode($dirty->IDs ?? '[]');
                    $ids[] = $this->owner->ID;
                    $dirty->IDs = json_encode($ids);
                    $dirty->write();
                    /** @var LoggerInterface $logger */
                    $logger = Injector::inst()->get(LoggerInterface::class);
                    $logger->error($e->getMessage(), $e->getTrace());
                }
            }
        }
    }

    /**
     * Reindex after write, if it's an indexed new/updated object
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if (
            !$this->owner->hasExtension(Versioned::class) ||
            ($this->owner->hasExtension(Versioned::class) && $this->owner->isPublished())
        ) {
            $this->doIndex();
        }
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    private function doIndex()
    {
        $list = DataObject::get($this->owner->ClassName, "ID = " . $this->owner->ID);
        /** @var ElasticCoreService $service */
        $service = Injector::inst()->get(ElasticCoreService::class);
        foreach ($service->getValidIndexes() as $indexStr) {
            /** @var ElasticIndex $index */
            $index = Injector::inst()->get($indexStr);
            $idxConfig = ElasticIndex::config()->get($index->getIndexName());
            if (in_array($this->owner->ClassName, $idxConfig['Classes'])) {
                $service->updateIndex($index, $list);
            }
        }
    }
}

<?php

namespace Firesphere\ElasticSearch\Extensions;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
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

/**
 * Class \Firesphere\ElasticSearch\Extensions\DataObjectElasticExtension
 *
 * @property DataObject|DataObjectSearchExtension|DataObjectElasticExtension $owner
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
            $config = ElasticIndex::config()->get($index);
            if (array_key_exists($this->owner->ClassName, $config)) {
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
                    $ids = json_decode($dirty->IDs);
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

    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }

    public function onAfterPublish()
    {

    }
}

<?php

namespace Firesphere\ElasticSearch\Tasks;

use Exception;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

/**
 * Example task to empty an Elastic index
 */
class BulkDeleteTask extends BuildTask
{
    /**
     * URLSegment of this task
     *
     * @var string
     */
    private static $segment = 'Truncastic';
    /**
     * My name
     *
     * @var string
     */
    protected $title = 'Truncate elastic core';
    /**
     * What do I do?
     *
     * @var string
     */
    protected $description = 'Try to remove everything from an Elastic core, to start fresh.';
    /**
     * @var bool
     */
    protected $enabled = false;

    /**
     * @param $request
     * @return void
     * @throws NotFoundExceptionInterface
     */
    public function run($request)
    {
        $service = Injector::inst()->get(ElasticCoreService::class);
        $pages = SiteTree::get();
        foreach ($pages as $page) {
            $q = [
                'index' => 'search-health',
                'body'  => [
                    'query' => [
                        'match' => [
                            'ObjectID' => $page->ID
                        ]
                    ]
                ]
            ];
            try {
                $service->getClient()->deleteByQuery($q);
            } catch (Exception $e) {
                // noop, just continue
            }
        }
    }
}

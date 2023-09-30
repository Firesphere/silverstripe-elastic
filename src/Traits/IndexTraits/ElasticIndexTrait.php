<?php
/**
 * Trait ElasticIndexTrait|Firesphere\ElasticSearch\Traits\ElasticIndexTrait Used to extract methods from the
 * {@link \Firesphere\ElasticSearch\Tasks\ElasticIndexTask} to make the code more readable
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Traits;

use Firesphere\ElasticSearch\Indexes\BaseIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;

/**
 * Trait ElasticIndexTrait
 * Getters and Setters for the ElasticIndexTask
 *
 * @package Firesphere\Elastic\Search
 */
trait ElasticIndexTrait
{
    /**
     * Debug mode enabled, default false
     *
     * @var bool
     */
    protected $debug = false;
    /**
     * Singleton of {@link ElasticCoreService}
     *
     * @var ElasticCoreService
     */
    protected $service;
    /**
     * @var BaseIndex Current core being indexed
     */
    protected $index;
    /**
     * @var int Number of CPU cores available
     */
    protected $cores = 1;
    /**
     * Default batch length
     *
     * @var int
     */
    protected $batchLength = 500;

    /**
     * Set the {@link ElasticCoreService}
     *
     * @param ElasticCoreService $service
     * @return self
     */
    public function setService(ElasticCoreService $service): self
    {
        $this->service = $service;

        return $this;
    }

    /**
     * Is this Index in debug mode
     *
     * @return bool
     */
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
     * Get the Index class.
     *
     * @return BaseIndex
     */
    public function getIndex(): BaseIndex
    {
        return $this->index;
    }

    /**
     * Set the index class
     *
     * @param BaseIndex $index
     */
    public function setIndex(BaseIndex $index): void
    {
        $this->index = $index;
    }

    /**
     * Get the amount of CPU Cores configured
     *
     * @return int
     */
    public function getCores(): int
    {
        return $this->cores;
    }

    /**
     * Set the amount of CPU Cores to use
     *
     * @param int $cores
     */
    public function setCores(int $cores): void
    {
        $this->cores = $cores;
    }

    /**
     * Get the length of a single batch
     *
     * @return int
     */
    public function getBatchLength(): int
    {
        return $this->batchLength;
    }

    /**
     * Set the length of a single batch
     *
     * @param int $batchLength
     */
    public function setBatchLength(int $batchLength): void
    {
        $this->batchLength = $batchLength;
    }
}

<?php
/**
 * class DocumentFactory|Firesphere\ElasticSearch\Factories\DocumentFactory Build a Solarium document to push
 *
 * @package Firesphere\Elastic\Search
 * @author Simon `Firesphere` Erkelens; Marco `Sheepy` Hermo
 * @copyright Copyright (c) 2018 - now() Firesphere & Sheepy
 */

namespace Firesphere\ElasticSearch\Factories;

use Exception;
use Firesphere\ElasticSearch\Indexes\ElasticIndex as ElasticBaseIndex;
use Firesphere\ElasticSearch\Services\ElasticCoreService;
use Firesphere\SearchBackend\Extensions\DataObjectSearchExtension;
use Firesphere\SearchBackend\Factories\DocumentCoreFactory;
use Firesphere\SearchBackend\Services\BaseService;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Class DocumentFactory
 * Factory to create documents to be pushed to Elastic
 *
 *  Firesphere\Elastic\Search
 */
class DocumentFactory extends DocumentCoreFactory
{
    use Configurable;
    use Extensible;

    /**
     * Note, it can only take one type of class at a time!
     * So make sure you properly loop and set $class
     *
     * @param array $fields Fields to index
     * @param ElasticBaseIndex $index Index to push the documents to
     * @param null $update Elastic doesn't have an "Update" object
     * @return array Documents to be pushed
     * @throws NotFoundExceptionInterface
     */
    public function buildItems($fields, $index, $update = null): array
    {
        $this->getFieldResolver()->setIndex($index);
        $docs = [];
        if ($this->debug) {
            $this->indexGroupMessage($index);
        }

        /** @var DataList|DataObject[] $item */
        foreach ($this->getItems() as $item) {
            // Don't index items that should not show in search explicitly.
            // Just a "not" is insufficient, as it could be null or false (both meaning, not set)
            if ($item->ShowInSearch === 0) {
                continue;
            }
            $doc = [];
            $doc = $this->buildFields($fields, $doc, $item);
            foreach ($index->getCopyFields() as $copyFieldName => $copyFields) {
                $doc[$copyFieldName] = $this->recursiveImplode($doc);
            }
            $doc = $this->addDefaultFields($doc, $item);

            $docs[] = $doc;
        }

        return $docs;
    }

    /**
     * Create the required record for a field
     *
     * @param array $fields Fields to build a record for
     * @param array $doc Document for Elastic
     * @param DataObject $item Object to get the data for
     * @throws Exception
     */
    protected function buildFields($fields, array $doc, DataObject $item): array //, array $boostFields): void
    {
        foreach ($fields as $field) {
            $fieldData = $this->getFieldResolver()->resolveField($field);
            foreach ($fieldData as $dataField => $options) {
                // Not an Elastic thing, for now
                //                $options['boost'] = $boostFields[$field] ?? null;
                $this->addField($doc, $item, $options);
            }
        }

        return $doc;
    }

    /**
     * Add a single field to the Elastic index
     *
     * @param array $doc Elastic Document
     * @param DataObject $object Object whose field is to be added
     * @param array $options Additional options
     */
    protected function addField(&$doc, $object, $options): void
    {
        if (!$this->classIs($object, $options['origin'])) {
            return;
        }

        $this->extend('onBeforeAddField', $options);

        $valuesForField = $this->getValuesForField($object, $options);

        foreach ($valuesForField as $value) {
            $this->extend('onBeforeAddDoc', $options, $value);
            $this->addToDoc($doc, $options, $value);
        }
    }

    /**
     * Push field to a document
     *
     * @param array $doc Elastic document
     * @param array $options Custom options
     * @param DBField|string|null $value Value(s) of the field
     */
    protected function addToDoc(&$doc, $options, $value): void
    {
        /* Elastic requires dates in the form of a timestamp in milliseconds, so we need to normalize to GMT */
        if (str_contains($options['type'], 'Date')) {
            $value = strtotime((string)$value) * 1000;
        }

        $name = $this->getShortFieldName($options['name']);
        $name = str_replace('_', '.', $name);

        $doc[$name] = $value;//, $options['boost'], Document::MODIFIER_SET);
    }

    /**
     * Recursively implode the array with values
     * to a single text blob to use as the main indexed
     * @param array $arr
     * @return string
     */
    protected function recursiveImplode($arr): string
    {
        $return = [];
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $return[] = $this->recursiveImplode($value);
            } else {
                $return[] = $value;
            }
        }

        return implode(', ', $return);
    }

    /**
     * Add fields that should always be included
     *
     * @param array $doc Elastic Document
     * @param DataObject|DataObjectSearchExtension $item Item to get the data from
     */
    protected function addDefaultFields($doc, $item)
    {
        $doc[BaseService::ID_FIELD] = $item->ClassName . '-' . $item->ID;
        $doc[BaseService::CLASS_ID_FIELD] = $item->ID;
        $doc[ElasticCoreService::ID_KEY] = $this->keyService->generateKey($item);
        // Set a known ID, with field name _id, for Elastic
        $doc['ClassName'] = $item->ClassName;
        $hierarchy = ClassInfo::ancestry($item);
        $classHierarchy = [];
        foreach ($hierarchy as $lower => $camel) {
            $classHierarchy[] = $camel;
        }
        $doc['ClassHierarchy'] = $classHierarchy;
        $doc['ViewStatus'] = $item->getViewStatus();
        $this->extend('updateDefaultFields', $doc, $item);

        return $doc;
    }
}

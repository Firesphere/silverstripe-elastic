<?php

namespace Firesphere\ElasticSearch\Factories;

use Exception;
use Firesphere\ElasticSearch\Indexes\BaseIndex as ElasticBaseIndex;
use Firesphere\SearchBackend\Extensions\DataObjectSearchExtension;
use Firesphere\SearchBackend\Factories\DocumentCoreFactory;
use Firesphere\SearchBackend\Helpers\DataResolver;
use Firesphere\SearchBackend\Services\BaseService;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Class DocumentFactory
 * Factory to create documents to be pushed to Solr
 *
 * @package Firesphere\Elastic\Search
 */
class DocumentFactory extends DocumentCoreFactory
{
    use Configurable;
    use Extensible;

    /**
     * @var bool Debug this build
     */
    protected $debug = false;

    protected $fieldResolver;


    /**
     * Note, it can only take one type of class at a time!
     * So make sure you properly loop and set $class
     *
     * @param array $fields Fields to index
     * @param ElasticBaseIndex $index Index to push the documents to
     * @param null $update Elastic doesn't have an "Update" object
     * @return array Documents to be pushed
     * @throws Exception
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
            $doc['_text'] = $this->recursiveImplode($doc);
            $doc = $this->addDefaultFields($doc, $item);

            $docs[] = $doc;
        }

        return $docs;
    }

    /**
     * @return mixed|object|Injector
     */
    public function getFieldResolver(): mixed
    {
        return $this->fieldResolver;
    }

    /**
     * @param mixed|object|Injector $fieldResolver
     */
    public function setFieldResolver(mixed $fieldResolver): void
    {
        $this->fieldResolver = $fieldResolver;
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
     * Add a single field to the Solr index
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
     * Determine if the given object is one of the given type
     *
     * @param string|DataObject $class Class to compare
     * @param array|string $base Class or list of base classes
     * @return bool
     */
    protected function classIs($class, $base): bool
    {
        $base = is_array($base) ? $base : [$base];

        foreach ($base as $nextBase) {
            if ($this->classEquals($class, $nextBase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a base class is an instance of the expected base group
     *
     * @param string|DataObject $class Class to compare
     * @param string $base Base class
     * @return bool
     */
    protected function classEquals($class, $base): bool
    {
        return $class === $base || ($class instanceof $base);
    }

    /**
     * Use the DataResolver to find the value(s) for a field.
     * Returns an array of values, and if it's multiple, it becomes a long array
     *
     * @param DataObject $object Object to resolve
     * @param array $options Customised options
     * @return array
     */
    protected function getValuesForField($object, $options): array
    {
        try {
            $valuesForField = [DataResolver::identify($object, $options['fullfield'])];
        } catch (Exception $error) {
            // @codeCoverageIgnoreStart
            $valuesForField = [];
            // @codeCoverageIgnoreEnd
        }

        return $valuesForField;
    }

    /**
     * Push field to a document
     *
     * @param array $doc Solr document
     * @param array $options Custom options
     * @param DBField|string|null $value Value(s) of the field
     */
    protected function addToDoc(&$doc, $options, $value): void
    {
        /* Solr requires dates in the form 1995-12-31T23:59:59Z, so we need to normalize to GMT */
        if ($value instanceof DBDate) {
            $value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
        }

        $name = getShortFieldName($options['name']);

        $doc[$name] = $value;//, $options['boost'], Document::MODIFIER_SET);
    }

    protected function recursiveImplode($arr)
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

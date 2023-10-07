<?php

namespace Firesphere\ElasticSearch\Factories;

use Exception;
use Firesphere\ElasticSearch\Indexes\BaseIndex as ElasticBaseIndex;
use Firesphere\SearchBackend\Extensions\DataObjectSearchExtension;
use Firesphere\SearchBackend\Factories\DocumentCoreFactory;
use Firesphere\SearchBackend\Helpers\FieldResolver;
use Firesphere\SearchBackend\Services\BaseService;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

/**
 * Class DocumentFactory
 * Factory to create documents to be pushed to Solr
 *
 * @package Firesphere\Solr\Search
 */
class DocumentFactory extends DocumentCoreFactory
{
    use Configurable;
    use Extensible;
    use DocumentFactoryTrait;
    /**
     * @var bool Debug this build
     */
    protected $debug = false;

    protected $fieldResolver;

    /**
     * DocumentFactory constructor, sets up the field resolver
     */
    public function __construct()
    {
        $this->fieldResolver = Injector::inst()->get(FieldResolver::class);
    }

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

        foreach ($this->getItems() as $item) {
            // Don't index items that should not show in search explicitly.
            // Just a "not" is insufficient, as it could be null or false (both meaning, not set)
            if ($item->ShowInSearch === 0) {
                continue;
            }
            /** @var array $doc */
            $doc = $this->createDocument();

            $docs[] = $doc;
        }

        return $docs;
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
        $doc['ClassName'] = $item->ClassName;
        $hierarchy = ClassInfo::ancestry($item);
        $classHierarchy = [];
        foreach ($hierarchy as $lower => $camel) {
            $classHierarchy[] = $camel;
        }
        $doc['ClassHierarchy'] = $classHierarchy;
        $doc['ViewStatus'] = $item->getViewStatus();
        $this->extend('updateDefaultFields', $doc, $item);
    }

    /**
     * Create the required record for a field
     *
     * @param array $fields Fields to build a record for
     * @param Document $doc Document for Solr
     * @param DataObject $item Object to get the data for
     * @param array $boostFields Custom set of index-time-boosted fields
     * @throws Exception
     */
    protected function buildFields($fields, Document $doc, DataObject $item, array $boostFields): void
    {
        foreach ($fields as $field) {
            $fieldData = $this->getFieldResolver()->resolveField($field);
            foreach ($fieldData as $dataField => $options) {
                // Not an Elastic thing, for now
//                $options['boost'] = $boostFields[$field] ?? null;
                $this->addField($doc, $item, $options);
            }
        }
    }

    /**
     * Add a single field to the Solr index
     *
     * @param Document $doc Solr Document
     * @param DataObject $object Object whose field is to be added
     * @param array $options Additional options
     */
    protected function addField($doc, $object, $options): void
    {
        if (!$this->classIs($object, $options['origin'])) {
            return;
        }

        $this->extend('onBeforeAddField', $options);

        $valuesForField = $this->getValuesForField($object, $options);

        $typeMap = Statics::getTypeMap();
        $type = $typeMap[$options['type']] ?? $typeMap['*'];

        foreach ($valuesForField as $value) {
            $this->extend('onBeforeAddDoc', $options, $value);
            $this->addToDoc($doc, $options, $type, $value);
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
     * @param Document $doc Solr document
     * @param array $options Custom options
     * @param string $type Type of Solr field
     * @param DBField|string|null $value Value(s) of the field
     */
    protected function addToDoc($doc, $options, $type, $value): void
    {
        /* Solr requires dates in the form 1995-12-31T23:59:59Z, so we need to normalize to GMT */
        if (($value && $type === 'tdate') || $value instanceof DBDate) {
            $value = gmdate('Y-m-d\TH:i:s\Z', strtotime($value));
        }

        $name = getShortFieldName($options['name']);

        $doc->addField($name, $value, $options['boost'], Document::MODIFIER_SET);
    }

    /**
     * Are we debugging?
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set to true if debugging should be enabled
     *
     * @param bool $debug
     * @return DocumentFactory
     */
    public function setDebug(bool $debug): DocumentFactory
    {
        $this->debug = $debug;

        return $this;
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
}

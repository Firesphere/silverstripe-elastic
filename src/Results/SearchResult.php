<?php

namespace Firesphere\ElasticSearch\Results;

use Elastic\Elasticsearch\Response\Elasticsearch;
use Firesphere\ElasticSearch\Indexes\ElasticIndex;
use Firesphere\ElasticSearch\Queries\ElasticQuery;
use Firesphere\SearchBackend\Interfaces\SearchResultInterface;
use Firesphere\SearchBackend\Services\BaseService;
use Firesphere\SearchBackend\Traits\SearchResultGetTrait;
use Firesphere\SearchBackend\Traits\SearchResultSetTrait;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;
use stdClass;

class SearchResult extends ViewableData implements SearchResultInterface
{
    use SearchResultGetTrait;
    use SearchResultSetTrait;

    /**
     * @var ElasticQuery Query that has been executed
     */
    protected $query;
    /**
     * @var ElasticIndex Index the query has run on
     */
    protected $index;
    /**
     * @var stdClass|ArrayList|DataList|DataObject Resulting matches from the query on the index
     */
    protected $matches;

    /**
     * SearchResult constructor.
     * Funnily enough, the $result contains the actual results, and has methods for the other things.
     * See Solarium docs for this.
     *
     * @param Elasticsearch $result
     * @param ElasticQuery $query
     * @param ElasticIndex $index
     */
    public function __construct(Elasticsearch $result, ElasticQuery $query, ElasticIndex $index)
    {
        parent::__construct();
        $this->index = $index;
        $this->query = $query;
        $result = $result->asObject();
//        if ($query->hasSpellcheck()) {
//            $this->setSpellcheck($result->getSpellcheck())
//                ->setCollatedSpellcheck($result->getSpellcheck());
//        }
        $this->setMatches($result->hits->hits)
            ->setTotalItems($result->hits->total->value);
    }

    /**
     * Set the collated spellcheck string
     *
     * @param mixed $collatedSpellcheck
     * @return $this
     */
    public function setCollatedSpellcheck($collatedSpellcheck): self
    {
        /** @var Collation $collated */
        if (!$this->index->isRetry() && $collatedSpellcheck && ($collated = $collatedSpellcheck->getCollations())) {
            $this->collatedSpellcheck = $collated[0]->getQuery();
        }

        return $this;
    }

    /**
     * Set the spellcheck list as an ArrayList
     *
     * @param SpellcheckResult|null $spellcheck
     * @return SearchResult
     */
    public function setSpellcheck($spellcheck): self
    {
        $spellcheckList = [];

        if ($spellcheck && ($suggestions = $spellcheck->getSuggestion(0))) {
            foreach ($suggestions->getWords() as $suggestion) {
                $spellcheckList[] = ArrayData::create($suggestion);
            }
        }

        $this->spellcheck = ArrayList::create($spellcheckList);

        return $this;
    }

    /**
     * Get the matches as a Paginated List
     *
     * @return PaginatedList
     */
    public function getPaginatedMatches(): PaginatedList
    {
        $request = Controller::curr()->getRequest();
        // Get all the items in the set and push them in to the list
        $items = $this->getMatches();
        /** @var PaginatedList $paginated */
        $paginated = PaginatedList::create($items, $request);
        // Do not limit the pagination, it's done at Elastic level
        $paginated->setLimitItems(false)
            // Override the count that's set from the item count
            ->setTotalItems($this->getTotalItems())
            // Set the start to the current page from start.
            ->setPageStart($this->query->getStart())
            // The amount of items per page to display
            ->setPageLength($this->query->getRows());

        return $paginated;
    }

    /**
     * Get the matches as an ArrayList and add an excerpt if possible.
     * {@link static::createExcerpt()}
     *
     * @return ArrayList
     */
    public function getMatches(): ArrayList
    {
        $matches = $this->matches;
        $items = [];
        $idField = BaseService::ID_FIELD;
        $classIDField = BaseService::CLASS_ID_FIELD;
        foreach ($matches as $match) {
            $item = $this->asDataobject($match, $classIDField);
            if ($item !== false) {
                $this->createExcerpt($idField, $match, $item);
                $items[] = $item;
                $item->destroy();
            }
            unset($match);
        }

        return ArrayList::create($items)->limit($this->query->getRows());
    }

    /**
     * Set the matches from Solarium as an ArrayList
     *
     * @param array|stdClass $result
     * @return $this
     */
    protected function setMatches($result): self
    {
        $data = [];
        /** @var stdClass $item */
        foreach ($result as $item) {
            $data[] = ArrayData::create($item);
            if (!empty($item->highlight)) {
                $this->addHighlight($item->highlight, $item->_id);
            }
        }

        $this->matches = ArrayList::create($data);

        return $this;
    }

    /**
     * Check if the match is a DataObject and exists
     * And, if so, return the found DO.
     *
     * @param $match
     * @param string $classIDField
     * @return DataObject|bool
     */
    protected function asDataobject($match, string $classIDField)
    {
        if (!$match instanceof DataObject) {
            $class = $match->_source->ClassName;
            /** @var DataObject $match */
            $match = $class::get()->byID($match->_source->{$classIDField});
            if ($match && $match->exists()) {
                $match->__set('elasticId', $match->_id);
            }
        }

        return ($match && $match->exists()) ? $match : false;
    }

    /**
     * Generate an excerpt for a DataObject
     *
     * @param string $idField
     * @param $match
     * @param DataObject $item
     */
    protected function createExcerpt(string $idField, $match, DataObject $item): void
    {
        $item->Excerpt = DBField::create_field(
            'HTMLText',
            str_replace(
                '&#65533;',
                '',
                $this->getHighlightByID($match->{$idField})
            )
        );
    }

    /**
     * Get the highlight for a specific document
     *
     * @param $docID
     * @return string
     */
    public function getHighlightByID($docID): string
    {
        $highlights = [];
        if ($this->highlight && $docID) {
            $highlight = (array)$this->highlight[$docID];
            foreach ($highlight as $field => $fieldHighlight) {
                $highlights[] = implode(' (...) ', $fieldHighlight);
            }
        }

        return implode(' (...) ', $highlights);
    }

    /**
     * Allow overriding of matches with a custom result. Accepts anything you like, mostly
     *
     * @param stdClass|ArrayList|DataList|DataObject $matches
     * @return mixed
     */
    public function setCustomisedMatches($matches)
    {
        $this->matches = $matches;

        return $matches;
    }

    /**
     * {@inheritDoc}
     */
    public function createFacet($facets, $options, $class, array $facetArray)
    {
        return $facetArray;
    }

    /**
     * Elastic is better off using the add method, as the highlights don't come in a
     * single bulk
     *
     * @param stdClass $highlight The highlights
     * @param string $docId The *Elastic* returned document ID
     * @return SearchResultInterface
     */
    protected function addHighlight($highlight, $docId): SearchResultInterface
    {
        $this->highlight[$docId][] = (array)$highlight;

        return $this;
    }
}

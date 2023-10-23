[![PHPUnit tests](https://github.com/Firesphere/silverstripe-elastic/actions/workflows/unittests.yml/badge.svg?branch=main)](https://github.com/Firesphere/silverstripe-elastic/actions/workflows/unittests.yml)
[![codecov](https://codecov.io/gh/Firesphere/silverstripe-elastic/graph/badge.svg?token=B8iPqvuOSo)](https://codecov.io/gh/Firesphere/silverstripe-elastic)
[![Maintainability](https://api.codeclimate.com/v1/badges/92a58f5679dfe201d774/maintainability)](https://codeclimate.com/github/Firesphere/silverstripe-elastic/maintainability)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Firesphere/silverstripe-elastic/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/Firesphere/silverstripe-elastic/?branch=main)

# Modern Silverstripe Elastic search

## Installation

`composer require firesphere/elastic`

### Requirements

- PHP8+
- Elasticsearch 8.10+
- Silverstripe Framework 4 || 5

## Elastic search with Silverstripe

This module provides an API similar to the [Solr Search](https://firesphere.github.io/solr-search) module.

### Basic search index

- Create an API-index in your ElasticSearch instance
    - If you have Elastic Enterprise, this is under Search => API
- Create an API key
    - You can use a username/password, but API key is recommended

### Configuring the service

Configuration is done in YML:

```yaml
---
Name: MyElastic
---
Firesphere\ElasticSearch\Services\ElasticCoreService:
  config:
    endpoint:
      - host: "https://my-elasticinstance.elastic-cloud.com"
        apiKey: "mybase64apikeyhere==="
        username: "Elastic"
        password: "mysupersecretpassword"
        port: 443
```

Take special note of the port. When using your own Elastic instance, this might be the standard port 9200.
On Elastic Cloud, it's all routed through a reverse proxy on port 443 (https).

*NOTE*
It's obviously never a great idea to use api keys or passwords in YML, but that's okay,
to configure it from environment:

```dotenv
ELASTIC_ENDPOINT=host.example.com
ELASTIC_USERNAME=user@example.com
ELASTIC_PASSWORD=examplepassword
ELASTIC_API_KEY=mybase64apikeyhere===
ELASTIC_PORT=443
ELASTIC_PROTOCOL=https
```

And in your YML:

```yaml
---
Name: MyElastic
---
Firesphere\ElasticSearch\Services\ElasticCoreService:
  config:
    endpoint: ENVIRONMENT
```

### Creating an index

An index has two parts, the class and the configuration.

The most basic class would by something like the following:

```php
<?php


namespace Firesphere\MyProject\Indexes;

use Firesphere\ElasticSearch\Indexes\ElasticIndex;

class ElasticProjectIndex extends ElasticIndex
{
    public function getIndexName()
    {
        return 'search-indexname';
    }
}

```

Where `search-indexname` is the name of the index you've chosen when configuring it in Elastic.

The accompanying YML that configures the fields would potentially look like this:

```yaml
Firesphere\ElasticSearch\Indexes\ElasticIndex:
  search-indexname:
    Classes:
      - Page
    FulltextFields:
      - Title
      - Content
      - Description
      - getElementsForSearch
      - Impression.Title
    FilterFields:
      - OwnerID
    FacetFields:
      Firesphere\MyProject\Models\Tag:
        BaseClass: Page
        Field: Tags.ID
        Title: Tag
```

This would at index time add those related fields in to the index, as well as at search runtime ensure
all the fields are properly added as filters, where needed.

### Further configuration

Please refer to the Solr documentation, and take the YML there as a guideline for configuring Elastic.

The goal is to have a near-identical API, which is largely the case already.

## Creating a search

In a controller of choice, here is an example of a search:

```php
class MyController extends PageController
{
    public function search()
    {   
        $query = $this->getRequest()->getVars();
        if (isset($query['query'])) {
            $baseQuery = new ElasticQuery();
            // Add the term
            $baseQuery->addTerm($query['query']);
            // Ensure to start at 0
            $start = isset($query['start']) ? $query['start'] : 0;
            $baseQuery->setStart($start);
            // Get the index
            $index = new ElasticProjectIndex();
            // And do the search
            $this->Results = $index->doSearch($baseQuery);
        }

        return $this;
    }
}
```

## Permissions

As with the Solr search, all documents are indexed with a `ViewStatus` field.
This field determines who can see the results. At search runtime, the value is calculated based on the current user
and as such passed in as an extra, required, filter.

### Further functionality

#### Done(~ish)

- [x] Basic filtering
- [x] Pagination
- [x] Actually, you know... search
- [x] Highlighting~ish
- [x] Synonyms
- [x] Group-access filtering (e.g. all, administrators, specific groups, from access setting in the CMS)
- [x] Boosting
- [x] Faceting
- [x] Spellchecking
- [x] Unit tests and integration tests

#### On the to-do list is:

- [ ] Work out the filtering better
- [ ] File content searching
- [ ] Submodules for
  - [ ] Member level permissions
  - [ ] Subsites
  - [ ] Fluent

# Cow?

Cow!

```

             /( ,,,,, )\
            _\,;;;;;;;,/_
         .-"; ;;;;;;;;; ;"-.
         '.__/`_ / \ _`\__.'
            | (')| |(') |
            | .--' '--. |
            |/ o     o \|
            |           |
           / \ _..=.._ / \
          /:. '._____.'   \
         ;::'    / \      .;
         |     _|_ _|_   ::|
       .-|     '==o=='    '|-.
      /  |  . /       \    |  \
      |  | ::|         |   | .|
      |  (  ')         (.  )::|
      |: |   |;  U U  ;|:: | `|
      |' |   | \ U U / |'  |  |
      ##V|   |_/`"""`\_|   |V##
         ##V##         ##V##
```

# Sponsors

// @todo Firesphere needs to get some sponsor logos (And maybe some sponsors?)

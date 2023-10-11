# Modern Silverstripe Elastic search

** This module is made possibly by [Pikselin](https://pikselin.com) **

## Elastic search with Silverstripe

This module provides an API similar to the [Solr Search](https://firesphere.github.io/solr-search] module).

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

### Creating an index

An index has two parts, the class and the configuration.

The most basic class would by something like the following:

```php
<?php


namespace Firesphere\MyProject\Indexes;

use Firesphere\ElasticSearch\Indexes\BaseIndex;

class ElasticProjectIndex extends BaseIndex
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
Firesphere\ElasticSearch\Indexes\BaseIndex:
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

## Permissions

As with the Solr search, all documents are indexed with a `ViewStatus` field.
This field determines who can see the results. At search runtime, the value is calculated based on the current user
and as such passed in as an extra filter.

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

# Set-up and configuration

## Getting started

In order to return search results, Elastic requires an Index that holds the searchable data.
So the first thing you _need_ to do is to create an index extending the
`Firesphere\ElasticSearch\Indexes\ElasticIndex` class.

If you are extending the base Index, it will require a `getIndexName` method
which is used to determine the name of the index to query Elastic.

**IMPORTANT**

The usage of `YML` for the core configuration is _not_ a replacement for creating your own Index
extending either of the Base Indexes; it is a complement to it.

`YML` is purely used for the configuration of the index Classes.

## Configuration

Configuring Elastic is done via YML:
```yaml
Firesphere\ElasticSearch\Services\ElasticCoreService:
  config:
    endpoint:
      myhostname:
        host: myhost.com
        port: 9200
        username: MyUsername
        password: MyPassword
        # set up timeouts
  # default path settings
  debug: false
```

### Authentication

Elastic supports several ways of adding authentication to the instance.

The module supports Basic Authentication or APIKey, which can be added in the YML config like so:
```yaml
Firesphere\ElasticSearch\Services\ElasticCoreService:
  config:
    endpoint:
      myhostname:
        username: Elastic
        password: ElasticRocks
        apiKey: MyBase64EncodedApiKeyHere===
```

#### ShowInSearch

`ShowInSearch` is handled by the module itself, so there is no need to configure it within your YML/PHP index definition. 
When a content author sets this field to `0` via the CMS, then the related Page or File object is actually _removed_ from the applicable Elastic 
core immediately through the `onAfterPublish` or `onAfterWrite` method, or during the next run of the `ElasticIndexJob`.

Therefore, custom addition of `ShowInSearch` as a filterable or indexable field in YML
is likely to cause unexpected behaviour.

The reason for removing `ShowInSearch = false|0` from the indexing process, 
is to streamline the number of items stored in Elastic's indexes. 
There is no effective need for items to be in the search, if they're not supposed to 
be displayed.

#### Dirty classes

If a change fails to update, a `DirtyClass` is created, recording the need for updating
said object. It is recommended to automatically run the `ClearDirtyClasses` task every few hours
depending on the expected amount of changes daily and the importance of those changes.

The expected time to run the task is quite low, so we recommend running this task reasonably
 often (every 5 or 10 minutes).

 

### Using YML

```yaml
Firesphere\ElasticSearch\Indexes\ElasticIndex:
  MySearchIndex:
    Classes:
      - SilverStripe\CMS\Model\SiteTree
    FulltextFields:
      - Content
      - TestObject.Title
      - TestObject.TestRelation.Title
    SortFields: 
	  - Created
    FilterFields:
      - Title
      - Created
      - Firesphere\ElasticSearch\Tests\TestObject
    BoostedFields:
	  - Title
    FacetFields:
      Firesphere\ElasticSearch\Tests\TestObject:
        BaseClass: SilverStripe\CMS\Model\SiteTree
        Field: ID
        Title: TestObject

```

#### MySearchIndex

This name should match the name you provided in your Index extending the `ElasticIndex` you are instructed
to create in the first step of this document.

## Grouped indexing

Be aware that Grouped indexing is `0`-based. Thus, if there are 150 groups to index,
the final group to index will be 149 instead of 150.

## Method output casting

To get the correct Elastic field type in the Elastic Configuration, you will need to add a
casting for each method you want to add. So for the `Content` field, the method below:

```php
public function getContent()
{
    return $renderedContent;
}
```

Could have a casting like the below to ensure it renders as HTML:

```php
private static $casting = [
    'getContent' => 'HTMLText',
    'Content'    => 'HTMLText'
];
```

Depending on your field definition, you either need to have the full method name, or the short method name.

## Another way to set the config in PHP

You could also use PHP to set the config. For readability however, it's better to use variables for Facets:
```php
    protected $facetFields = [
        RelatedObject::class   => [
            'BaseClass' => SiteTree::class,
            'Field'     => 'RelatedObjectID',
            'Title'     => 'RelationOne'
        ],
        OtherRelatedObject::class => [
            'BaseClass' => SiteTree::class,
            'Field'     => 'OtherRelatedObjectID',
            'Title'     => 'RelationTwo'
        ]
    ];
```

This will generate a facet field in Elastic, assuming this relation exists on `SiteTree` or `Page`.

The relation would look like `SiteTree.RelatedObjectID`, where `RelatedObject` the name of the relation reflects.

The Title is used to group all facets by their Title, in the template, this is accessible by looping `$Result.FacetSet.TitleOfTheFacet`

### Important notice

Facets are relational. For faceting on a relation, omit the origin class (e.g. `SiteTree`), but supply the full relational
path to the facet. e.g. if you want to have facets on `RelationObject->ManyRelation()->OneRelation()->ID`, the Facet declaration should be
`ManyRelationObject.OneRelationID`, assuming it's a `has_one` relation.

If you have many relations, through either `many_many` or `has_many`, your definition should 
use `ManyRelationObjectName.Relation.ID` instead of `RelationID`. It works and resolves the same.

It is strongly advised to use relations for faceting, as Elastic tends to think of textual relations in a different way.

#### Example

If you set relations on `MyObject.TextField`, and the text field contains "Content Name
One" and "Content Name Two", faceting would be done in such a way that "Content", "Name"
and "One" would be three different facet results, rather than "Content Name One".

## Accessing Elastic

If available, you can access your Elastic instance via Kibana, at `http://mydomain.com:5601`

## Excluding unwanted indexes

To exclude unwanted indexes, it is possible declare a list of _wanted_ indexes in the `YML`

```yaml
Firesphere\ElasticSearch\Services\ElasticCoreService:
  indexes:
    - CircleCITestIndex
    - Firesphere\ElasticSearch\Tests\TestIndex
    - Firesphere\ElasticSearch\Tests\TestIndexTwo
    - Firesphere\ElasticSearch\Tests\TestIndexThree
```

Looking at the `tests` folder, there is a `TestIndexFour`. This index is not loaded unless explicitly asked.

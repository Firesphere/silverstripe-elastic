# Boost queries

Boosting can be done at both Index time, if the configuration supports it, or at Query time.

To boost a certain query at Query time (easiest), use the following syntax:

**NOTE** With Solr, the field names are _underscored_. In Elastic, they are `.` dotted.

You'll need to prefix your boost field, with the base class it is on. E.g. for Page Title,
your boosted field is `SiteTree.Title`, not `Page.Title`, as SiteTree is the base class that holds
the Title.
```php
    $query->addBoostedField('Field.Name', $value);
```

Where `$value` is the boosting value. The default value is `1.0`, anything below that will decrease the
relevance, anything above increases it.

## Boosting a single term out of a set

To boost a single term specifically, or on a specific field, you can use the following:

```php
$query->addTerm('My search terms', ['Fields', 'To', 'Boost', 'On'], $value);
```

Where the array of fields should not be empty. `$value` is the amount of boosting that should be applied
to the fields in the array, for example `$value = 2` will mean that results are treated as twice as relevant.

This executes a global search for the term, followed by a boosting for each field in the
field array with a boost status of `$value`.

Note that the boosted fields do need to be added as a boosted field at Configure time.

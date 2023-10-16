# Elastic Suggest

Work in progress, try doing a search and see what the result `->getSpellcheck()` returns.

It is an ArrayList object, with ArrayData.

The ArrayData is constructed as follows:

```php
[
    'original' => 'Originally Spelled Word or Phrase',
    'suggestion' => 'Elastic suggestion based on all content'
]
```

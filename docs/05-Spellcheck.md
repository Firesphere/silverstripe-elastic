# Spellcheck

> I cand zpel gud

**NOTE** This is work in progress! The output is not definitive yet.

Spellchecking is enabled by default, and can be disabled
on query time, by setting `$query->setSpellcheck(false);`

Spellchecking is carried over to the search result returned.

To access the spellchecks, the following methods can be used:

## Word-based only spellchecking

Word based spellcheck returns only misspelled words. For example,
if the query is "hesp me", the word based spellcheck will return a list
of words that are possible alternatives for "hesp".

e.g.
- help
- helm
- hero

The resulting list can be accessed as an ArrayList, as the example below:

```html
<% if $Results.Spellcheck.Count %>
    <% loop $Results.Spellcheck %>
        $word
    <% end_loop %>
<% end_if %>
```

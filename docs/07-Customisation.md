# Customisation

## Extension points

All steps of the process, from index to searching, have extension points.

These extension points can be used to alter or update the respective steps.

Available extension points:

| Method | Used for | Available on |
| ------ | -------- | ------------ |
| `onBeforeInit` | Update initialisation features | `BaseIndex` |
| `onAfterInit` | Update initialisation features | `BaseIndex` |
| `onBeforeSearch` | Before executing the search, update the query | `BaseIndex` |
| `onAfterSearch` | Manipulate the results | `BaseIndex` |
| `updateSearchResults` | Manipulate the returned result object | `BaseIndex` |


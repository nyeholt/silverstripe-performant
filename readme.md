# SilverStripe Performant

A module for pre-calculating a load of data about your page structure
to greatly speed up things like menu generation. 

Avoids the costly recursive tree lookups that things like `Children`, 
`Link`, `Parent` can trigger, but still applies a level of permission 
checking, and respects ShowInMenus settings.

## Maintainer Contact

Marcus Nyeholt

<marcus (at) silverstripe (dot) com (dot) au>

## Requirements

* SilverStripe 3.x

## Documentation

With a reference to the `SiteDataService`, you can access 

* `getItem()` - a `DataObjectNode` object
* `getItems()` - all page objects


`DataObjectNode` provides a partial API implementation for accessing methods typically
found on SiteTree, but where the items returned are `DataObjectNode`s looked up via 
the pre-cached data in `SiteDataService`

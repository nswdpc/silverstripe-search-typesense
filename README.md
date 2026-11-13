# Typesense Silverstripe integration

This module:

+ allows programmatic creation of search forms to query a Typesense collection
+ carrying out of Typesense searches on a selected collection
+ provides a consistent data structure for indexing and rendering results
+ supports adding Typesense instantsearch to your data models, with configuration within the administration area
+ provides an extension to handle upsert and removal of documents that are marked for indexing
+ import records to a Typesense server, via task or queued job
+ remove records from Typesense server

This module does not provide any implementations for searching in your Typesense collections. Use the following modules to implement this:

+ `nswdpc/silverstripe-typesense-cms` - provides a Typesense page to search and display results from collections
+ `nswdpc/silverstripe-typesense-elemental` - provides Elemental content blocks to search collections

For NSW users wanting to integrate with the NSW Design System, the module `nswdpc/waratah-typesense` will assist.

## Documentation

* [Start at the index](./docs/en/001_index.md)

## Requirements

+ a Typesense server or servers

## Installation

```sh
composer require nswdpc/silverstripe-search-typesense
```

## License

[BSD-3-Clause](./LICENSE.md)

## Configuration

Environment:

### Management key

> [Avoid using the bootstrap API key as documented at](https://typesense.org/docs/30.2/api/api-keys.html#api-keys)
>
> The key can be limited to collections, have an expiry, [as defined here](https://typesense.org/docs/30.2/api/api-keys.html#create-an-api-key).

This key should have `actions` to:

- create collections (`collections:create`)
- check existence of a collection (`collections:get`, `collections:list`)
- import documents to collections (`documents:import`)
- upsert a document (`documents:upsert`)
- delete a document (`documents:delete`)
- read API keys, for validating search-only keys (`keys:list`)

Optionally:

- delete collections (`collections:delete`)

```sh
TYPESENSE_API_KEY="API key that can read and write"
```

### Server endpoint

Add one or more endpoints for Typesense. Multiple endpoints are separated by a comma.

```sh
TYPESENSE_SERVER="https://host:port"
```

### Search-only key

Set a search-only key, this will be used to create scoped search keys. It must only have the action `documents:search`.

> The key can be limited to collections, have an expiry, [as defined here](https://typesense.org/docs/30.2/api/api-keys.html#create-an-api-key).

```sh
TYPESENSE_SEARCH_KEY='Optional search-only key for creating scoped API keys for Instantsearch'
```

## Maintainers

+ PD web team

## Bugtracker

We welcome bug reports, pull requests and feature requests on the Github Issue tracker for this project.

Please review the [code of conduct](./code-of-conduct.md) prior to opening a new issue.

## Security

If you have found a security issue with this module, please email digital[@]dpc.nsw.gov.au in the first instance, detailing your findings.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.

Please review the [code of conduct](./code-of-conduct.md) prior to completing a pull request.

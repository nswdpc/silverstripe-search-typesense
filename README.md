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

```sh
TYPESENSE_API_KEY="API key that can read and write"
TYPESENSE_SERVER="https://host:port"
TYPESENSE_SEARCH_KEY='Optional search only key for creating scoped API keys for Instantsearch'
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

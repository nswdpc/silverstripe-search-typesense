# Documentation

## General

Prior to implementing a Typesense search solution in your project, your site administrator to read and understand the Typesense documentation available. Important concepts are:

1. Typesense server setup (self hosted or in the Typesense cloud)
1. How to import records into Typesense
1. [How to manage access to data and API keys](https://typesense.org/docs/guide/data-access-control.html)

The current documentation is available at: [https://typesense.org/docs/guide/](https://typesense.org/docs/guide/)

## Collections

In the current implementation of this module, a Typesense collection represents the indexable records of a single Silverstripe DataObject model e.g. for all searchable pages, the SiteTree model should be configured as a collection.

The `nswdpc/silverstripe-typesense-cms` provides a module that sets up an indexable collection of SiteTree records (all types of pages).

To access the configured or created collections, visit the "Typesense Search" administration screen. Select 'Collections'.

Administrators [should read about creating collection configuration and indexing](./0011_collection_configuration.md)).

### Cluster size

The remote Typesense server will have restrictions on the maximum size of data that can be stored. If a reindex fails, it may be because the cluster has reached its maximum size.

A site administrator should size clusters based on the expected size of the data being indexed.

### Indexing a collection

> The total time to index a collection depends on the size of the collection.

#### Via the collection edit screen

For those with access, visit the "Typesense Search" administration screen. Select 'Collections'. If there are pre-configured collections in configuration they will display here (provided a dev/build has been run).

Click on a collection to bring up its edit screen. If you have the required permission, you will see a "Refresh the search index" button. If you do not see this and think you should have access to this, request access from an administrator.

Refreshing the search index will kick off a job that will run over time to import searchable documents into a collection at the remote Typesense server. If the collection does not exist at the remote, it will be created.

#### Via an import task

Administrators will have access to an import task that will import a collection of records in a batched process over time.

### Deleting a collection

Deleting a collection in the Collections administration area will cause any search frontends using that collection to become inoperable.

### Collection enabled option

To make a collection available to search frontends, check its 'Collection enabled' checkbox.

If you uncheck this box, it may result in inoperable search functionality.

### Creating and updating a collection

Creating and updating a collection should be undertaken by an administrator with Typesense knowledge. Modiying the collection metadata can cause searches to become non-functional.

To test a new schema / collection - create a new collection, test it, and when you are satisified it is working, switch the relevant search fields/forms to use that collection record.

### Naming

It can be useful to name your collections with versions e.g "Pages v1.0".

## Instantsearch

+ [InstantSearch](./002_instantsearch.md)

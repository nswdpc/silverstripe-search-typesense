# Documentation

+ [Index](./001_index.md)

## Collection configuration

> A Typesense collection represents a subset of records from a specific DataObject that can be selected for indexing.

### Example:

To set up a specific data model in your project as as indexable, add the following configuration to your project.

Review the [Typesense documentation](https://typesense.org/docs/29.0/api/collections.html#schema-parameters) for all the available values and their descriptions.

The "name" value corresponds with the field name of a DataObject DB field.

```yml
# Name: 'can be any value you want'
---
Name: 'app-typesense-search-collections'
---
NSWDPC\Search\Typesense\Models\TypesenseSearchCollection:
  collections:
    App\Project\People:
      # required
      name: 'People v1'
      # required
      fields:
        - 
          name: Title
          type: string
          sort: true
        - 
          name: Name
          type: string
          sort: true
        - 
          name: Nickname
          type: string
          sort: true
        - 
          name: Stars
          type: int32
          sort: true
        - 
          name: SomethingNotIndexed
          type: string
          index: false
        - 
          name: SomethingFaceted
          type: string
          facet: true
      # optional - see documentation
      # e.g. add hyphen to split words by hyphen
      token_separators:
        - '-'
      symbols_to_index:
        - '+'
      # optional - see documentation
      default_sorting_field: 'Stars'
```
### Default fields

A document sent to Typesense server will always have the following default fields added:

- 'id' .. the record.ID value
- 'ClassName' .. the record.ClassName value
- 'Created' and 'LastEdited' .. converted to timestamps
- 'TypesenseSearchResultData' .. a special object data structure, see below

### Finding the field value

The `TypesenseDocument` class is used to return a document for indexing, if the record class or an extension of it does not have a method `getTypesenseDocument`. If the latter, that method is used - see the DocumentDataExtension for a simple implementation.

By default, the values for the DataObject record passed to the TypesenseDocument::get() method are determined by the following methods named in this order:

1. A direct method on the record (not an Extension of the DataObject) `getTypesenseValueFor{FieldName}` where `{FieldName}` is the name of the field. E.g. if the record has a method `getTypesenseValueForPopularity` and a field being indexed is named "Popularity", the value returned from this method will be saved to the document for indexing.
1. A direct method on the record (not an Extension of the DataObject) `get{FieldName}` - as in `getPopularity`.
1. The standard `getField('Popularity')` getter handling in a DataObject.

If the field to be indexed is a `DB` field, the value will be updated:
1. Any field that is a DBHTMLText or DBHTMLVarchar will be rendered as plain text.
2. Any field that is an int64 and is a DBDate/DBDatetime type will be converted to a UNIX timestamp per Typesense documentation

For custom implementations, you must return the value as the type the schema expects. For a string field, return a string, int field requires an int value.

## Versioning

By default only records from the LIVE stage are selected for indexing. Draft records are not indexed. Un-versioned data models are supported.

## Ignoring records

If a record should not be indexed, the document value should be set to an empty array. Review the `getRecords` method of `TypesenseSearchCollection` to see how this is achieved:

1. If the record has a field 'ShowInSearch' all records with ShowInSearch=0 will be excluded.
1. Use the extension method `onGetRecordsForTypesenseIndexing` on an extension to the indexed DataObject class to further remove records from the list of records returned. The parameter passed is the DataList of records.
1. When importing record, each record in the datalist is processed for relevant values. If the returned value is an empty array it will be ignored.

### TypesenseSearchResultData

This field contains an object that can be used for round-trip search results. The default implementation of Instantsearch in this module requires a 'TypesenseSearchResultData' value to be set on each indexed record.

This allows search results to be returned without the need to look up the local database for field values.

If the record being indexed has a method `getTypesenseSearchResultData` that value will be sent to the search server during indexed. The value is an array, with keys/values being field names / field values. Review the `TypesenseDefaultFields` trait for an example and the `SiteTreeSearchResult` in the `nswdpc/silverstripe-typesense-cms` module for a SiteTree implementation of this.

#### Usage

In an Instantsearch configuration, you could enter the field name `TypesenseSearchResultData.Link` as the value for "The property on the 'hit' that holds the link to the result". The link returned will be used as the link value in the "hit".



# Documentation

+ [Index](./001_index.md)

## InstantSearch

### About

This module implements instant search using the Typesense InstantSearch Adapter. The implementation binds to a current search field in a website and renders a hitbox containing results based on the configuration set.

> The current implentation requires a site administrator to have an understanding of the HTML elements on their site.
> To create Instantsearch configurations, your site administrator should understand the concepts of Search-only API keys, search scopes and have a working Typesense search server set up.

#### Examples

1. You have search form in your website pulling results from a collection in Typesense. You can create an Instantsearch configuration and attach it to the form to provide results in-situ.
1. You have an input field, and an Instantsearch configuration is created and references this field.

### Implementation

1. The model being rendered in a template must have the InstantSearchExtension applied
2. In the template being used to render the model, add the following template code (in the scope of the model)

To add InstantSearch support to a current model, apply the InstantSearchExtension in your configuration. The template rendering that model will now have a `TypesenseInstantSearch` method accessible in the model scope:

```yml
My\App\SearchFormModel:
  extensions:
    - 'NSWDPC\Search\Typesense\Extensions\InstantSearchExtension'
```

Template e.g. templates/My/App/SearchFormModel.ss:
```html
<% include NSWDPC/Search/Typesense/InstantSearchResults %>
```

3. If the model is linked to an enabled InstantSearch configuration, an HTML tag with the relevant configuration will be added to the page in that location: `<div data-instantsearch="{...}"></div>`
4. The Javascript library will discover this configuration tag and attempt to bind an instantsearch instance to it.
5. If successful, typing a query into the relevant search field will return results.
6. The data being indexed should use the trait TypesenseDefaultFields and return values for the result in the method `getTypesenseSearchResult`

Remember to re-index your data either via the import task or the the synchronisation job.

### Configuring a model

Provided you have the relevant permissions, navigate to 'Typesense Search' in the administration area of your website. If you cannot see this but require access, an administrator can set up access.

Add a configuration or edit a current one.

If you edit a current one, be aware that changes **may make your search inoperable**. It is better to create a new configuration, test it, and when working as expected enable it and point the relevant search elements at it.

Testing on a hidden page on your site is a useful method.

### Search scope

A search scope is a powerful way of limiting what a scoped API key can access. You can add any search parameters listed in the Typesense document (https://typesense.org/docs/29.0/api/search.html#search-parameters) to a scope.

Add a search scope using the "Search scope" field in configuration. The search scope must be provided as a valid JSON-formatted value.

#### Fields

##### General
+ **Title**: an internal value used by CMS Editors to pick the relevant search configuration. Be descriptive to help them.
+ **Enabled**: whether to enable this configuration or not

##### API/Server
+ **Search-only key**: your search-only API key with a single aciton of 'documents:search'. A scoped API key will be created from this using the scope you set. If you do not provide one, the value of the environment variable `TYPESENSE_SEARCH_KEY` will be used to create a Scoped search API key using the search scope provided (see below). The scoped API key is added to your HTML source.
+ **Search scope (JSON)**: enter JSON values for a search scope. This is advanced configuration and requires knowledge of search scopes.
+ **Server nodes**: your Typesense server nodes.Add the full URL of each server node, one per line, including the port.
+ **Fields to query**: query these fields in your search. Relevant to the collection chosen

##### Searchbox
+ **Id attribute of the field**: in your page, the search input should have an "id" attribute. Enter this value in this field to bind the configuration to the search field on your webpage.
+ **Field prompt**: the placeholder value for the search input, optional
+ **Instructions for screen readers**: provide instructions for people using screen readers.

##### Hitbox
+ **Id attribute of the parent container**: the hitbox containing results will be appended to this element
+ **The property on the 'hit' that holds the link to the result**: enter a property on the hit e.g. TypesenseSearchResultData.Link, without this a link to a result cannot be created. Example: If a search hit holds the value for the link to the result in the "ResultLink" property, enter "ResultLink" here.
+ **The property on the 'hit' that holds the title of the result**: ditto as above, but for the title of the result.
+ **The property on the 'hit' that holds the abstract of the result**: ditto, but not yet implemented

Once configured, find your model in the CMS that references the search form in question, navigate to its "Instant search" tab and choose  the configuration you have just created. Authors can also choose enabled Instantsearch configurations.

### Verifying

Test out your search, and if all goes well you will see results. If not, observe errors in the browser console as you search.

### Examples

### Site-wide search
If you have a site-wide search, add the following to a template that is loaded on every page the site-wide search form appears on:

```html
<% with $SiteConfig %>
    <% include NSWDPC/Search/Typesense/InstantSearchResults %>
<% end_with %>
```
In your site settings, on the "Instant search" tab, choose a configuration that represents your site-wide search, then save.


### Elemental example

Have a look at the `nswdpc/silverstripe-typesense-elemental` module, specifically the TypesenseSearchElement model which has the `InstantSearchExtension` applied and its corresponding template.


### Troubleshooting

> The browser console will generally show errors that assist in determining issues.

#### Results are not showing
1. By default the hitbox relies on there being a "TypesenseSearchResultData" and "Title" property in the hits returned from Typesense multi-search. If this is not the case, results will not show.

```json
"document": {
    "Title": "Document title",
    "TypesenseSearchResultData": { "key": "value", ... }
},
"document":....etc
```

2. The Typesense server URL, search scope or API key are incorrect. You may have added a search scope that cannot be used to run a search. Please add a valid search scope.
3. The collection chosen does not exist at the Typesense server
4. The search-only API key is not allowed to access the collection chosen
5. A field name in the "Fields to query" field of the configuration does not exist in the collection to be searched.
6. There is no Typesense search-only API key in configuration

#### The link is not correct in the result
1. Make sure the link and title fields are returned in the list of documents. If you type and get results, inspect the fields returned.

### No configuration tag is added for my search form
1. It is possible the configuration is not enabled
2. It is possible the configuration is invalid
3. The model does not have the InstantSearchExtension applied
4. The <% include %> is added in the wrong scope

Check the configuration for errors, incorrect 'id' attribute values and the like.

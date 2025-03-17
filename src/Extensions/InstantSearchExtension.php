<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\Collection;
use NSWDPC\Search\Typesense\Services\InstantSearch;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;

/**
 * Extension applied to models that can provide instantsearch interface
 * e.g elemental content blocks providing a search interface
 */
class InstantSearchExtension extends DataExtension {

    private static array $db = [
        'UseInstantSearch' => 'Boolean',
        'InstantSearchPrompt' => 'Varchar(255)',
        'InstantSearchKey' => 'Varchar(255)',
        'InstantSearchNodes' => 'Text',
        'InstantSearchQueryBy' => 'Varchar(255)',
        'InstantSearchCollectionName' => 'Varchar(255)'
    ];

    private static array $has_one = [
        'InstantSearchCollection' => Collection::class
    ];

    private static array $defaults = [
        'UseInstantSearch' => 0
    ];

    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldsToTab(
            'Root.InstantSearch',
            [
                CheckboxField::create(
                    'UseInstantSearch',
                    _t(static::class . '.USE_INSTANT_SEARCH', 'Use "Instant Search"')
                ),
                TextField::create(
                    'InstantSearchKey',
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY', 'Public key')
                )->setDescription(
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY_WARNING', 'Use a Typesense "Search-only API Key" only, here. This will be included publicly in the website.')
                ),
                TextareaField::create(
                    'InstantSearchNodes',
                    _t(static::class . '.INSTANT_SEARCH_NODES', 'The server node(s), if different to the main configured Typesense server(s)'),
                )->setDescription(
                    _t(static::class . '.INSTANT_SEARCH_NODES_NOTES', 'One node per line, include protocol host and port e.g. https://search1.example.com:1890')
                )->setRows(3),

                DropdownField::create(
                    'InstantSearchCollectionID',
                    _t(static::class . '.INSTANT_SEARCH_COLLECTION_SELECT', 'Choose a Collection to search'),
                    Collection::get()->filter(['Enabled' => 1])->sort(['Name' => 'ASC'])->map('ID','Name')
                )->setEmptyString(''),
                TextField::create(
                    'InstantSearchCollectionName',
                    _t(static::class . '.INSTANT_SEARCH_COLLECTION', '...or enter a collection name')
                ),
                TextField::create(
                    'InstantSearchQueryBy',
                    _t(static::class . '.INSTANT_SEARCH_PROMPT', 'Fields to query. Separate fields by a comma')
                ),
                TextField::create(
                    'InstantSearchPrompt',
                    _t(static::class . '.INSTANT_SEARCH_PROMPT', 'Field prompt, optional')
                )
            ]
        );
    }

    /**
     * Retrieve nodes for Typesense instantsearch usage
     */
    protected function getTypesenseNodes(): array {
        $nodes = [];
        $searchNodes = preg_split("/[\n\r]+/", $this->getOwner()->InstantSearchNodes);
        if(is_array($searchNodes)) {
            foreach($searchNodes as $searchNode) {
                $url = parse_url($searchNode);
                $scheme = $url['scheme'] ?? '';
                $host = $url['host'] ?? '';
                $port = $url['port'] ?? '';
                if(!$port) {
                    $port = ($scheme == "https" ? 443 : 80);
                }
                $path = $url['path'] ?? '';
                if(!isset($scheme)) {
                    throw new ValidationException(_t(static::class . '.INSTANT_SEARCH_INVALID_URL_SCHEME', 'URL {url} does not include a scheme', ['url' => $searchNode]));
                }
                if(!isset($host)) {
                    throw new ValidationException(_t(static::class . '.INSTANT_SEARCH_INVALID_URL_HOST', 'URL {url} does not include a host', ['url' => $searchNode]));
                }
                $nodes[] = [
                    'host' => $host,
                    'port' => $port,
                    'protocol' => $scheme,
                    'path' => $path
                ];
            }
        }
        return $nodes;
    }

    /**
     * Perform validation if nodes are set
     */
    public function onBeforeWrite() {
        $this->getTypesenseNodes();
    }

    /**
     * Return a unique ID for the searchbox and
     * Users of this extension can implement their own value
     * E.g elements can use the getAnchor() method return value
     */
    public function getTypesenseUniqID(): string {
        return bin2hex(random_bytes(4));
    }

    /**
     * The model using this extension needs to provide an input ID to bind to
     */
    public function getTypesenseBindToInputId(): string {
        return "";
    }

    /**
     * The model using this extension needs to provide the ID of a parent used to locate
     * the hits box
     */
    public function getTypesenseBindToParentId(): string {
        return "";
    }

    /**
     * template method for getting the element's unique ID in the DOM
     */
    public function TypesenseUniqID(): string {
        return $this->getOwner()->getTypesenseUniqID();
    }

    /**
     * Get the collection name
     * If the model using this extension has a getCollection, this can be used provided
     * the collection name is not set by this extension's CMS fields
     */
    public function getCollectionName(): string {
        $collectionName = '';
        // select via relation first, if set
        $collection = $this->getOwner()->InstantSearchCollection();
        if($collection && $collection->isInDB()) {
            $collectionName = $collection->Name;
        }
        // a static name provided in the field overrules
        if($this->getOwner()->InstantSearchCollectionName) {
            $collectionName = $this->getOwner()->InstantSearchCollectionName;
        }
        // if not set, try to owner model
        if(!$collectionName && $this->getOwner()->hasMethod('getCollection')) {
            $collection = $this->getOwner()->getCollection();
            if($collection && ($collection instanceof Collection)) {
                $collectionName = $collection->Name;
            }
        }

        return (string)$collectionName;
    }

    /**
     * Template method to process and render the instantsearch interface and requirements
     * See: https://github.com/typesense/typesense-instantsearch-adapter?tab=readme-ov-file#with-instantsearchjs
     * Templates using typesense instantsearch should add the include to their template:
     * <% include NSWDPC/Search/Typesense/InstantSearchResults %>
     * The include will call this method
     */
    public function TypesenseInstantSearch(): ?DBHTMLText {
        if($this->getOwner()->UseInstantSearch == 0) {
            return null;
        }
        $collectionName = $this->getOwner()->getCollectionName();
        if($collectionName === '') {
            return null;
        }

        $id = $this->getOwner()->getTypesenseUniqID();
        $inputId = $this->getOwner()->getTypesenseBindToInputId();
        $parentId = $this->getOwner()->getTypesenseBindToParentId();
        $apiKey = $this->getOwner()->InstantSearchKey;
        try {
            $nodes = $this->getTypesenseNodes();
        } catch (\Exception $e) {
        }
        $queryBy = $this->getOwner()->InstantSearchQueryBy;
        if(!$queryBy) {
            $queryBy = 'Title';
        }
        $serverExtra = [
            'cacheSearchResultsForSeconds' => 120
        ];

        $data = [
            'id' => $id,
            'inputId' => $inputId,
            'parentId' => $parentId,
            'apiKey' => $apiKey,
            'queryBy' => $queryBy,
            'nodes' => $nodes,
            'collectionName' => $collectionName,
            'serverExtra' => $serverExtra
        ];

        // Add instantsearch
        return InstantSearch::provide($data);
    }
}

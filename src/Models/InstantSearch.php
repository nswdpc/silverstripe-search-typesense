<?php

namespace NSWDPC\Search\Typesense\Models;

use ElliotSawyer\SilverstripeTypesense\Collection;
use NSWDPC\Search\Typesense\Services\InstantSearch as InstantSearchService;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Configuration model for instantsearch
 */
class InstantSearch extends DataObject implements PermissionProvider {

    private static string $table_name = "TypesenseInstantSearch";

    private static string $singular_name = "InstantSearch configuration";

    private static string $plural_name = "InstantSearch configurations";

    private static array $db = [
        'Title' => 'Varchar(255)',
        'Enabled' => 'Boolean',
        'Prompt' => 'Varchar(255)',
        'SearchKey' => 'Varchar(255)',
        'Nodes' => 'Text',
        'QueryBy' => 'Varchar(255)',
        'CollectionName' => 'Varchar(255)',
        'InputElementId' => 'Varchar(255)',
        'ContainerElementId' => 'Varchar(255)'
    ];

    private static array $has_one = [
        'Collection' => Collection::class
    ];

    private static array $indexes = [
        'Enabled' => true
    ];

    private static array $defaults = [
        'Enabled' => 0
    ];

    public function getCMSFields() {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main',
            [
                CheckboxField::create(
                    'Enabled',
                    _t(static::class . '.USE_INSTANT_SEARCH', 'Enabled')
                ),
                TextField::create(
                    'SearchKey',
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY', 'Public key')
                )->setDescription(
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY_WARNING', 'Use a Typesense "Search-only API Key" only, here. This will be included publicly in the website.')
                ),
                TextareaField::create(
                    'Nodes',
                    _t(static::class . '.INSTANT_SEARCH_NODES', 'The server node(s), if different to the main configured Typesense server(s)'),
                )->setDescription(
                    _t(static::class . '.INSTANT_SEARCH_NODES_NOTES', 'One node per line, include protocol host and port e.g. https://search1.example.com:1890')
                )->setRows(3),
                DropdownField::create(
                    'CollectionID',
                    _t(static::class . '.INSTANT_SEARCH_COLLECTION_SELECT', 'Choose a Collection to search'),
                    Collection::get()->filter(['Enabled' => 1])->sort(['Name' => 'ASC'])->map('ID','Name')
                )->setEmptyString(''),
                TextField::create(
                    'CollectionName',
                    _t(static::class . '.INSTANT_SEARCH_COLLECTION', '...or enter a collection name')
                ),
                TextField::create(
                    'QueryBy',
                    _t(static::class . '.INSTANT_SEARCH_PROMPT', 'Fields to query. Separate fields by a comma')
                ),
                TextField::create(
                    'Prompt',
                    _t(static::class . '.INSTANT_SEARCH_PROMPT', 'Field prompt, optional')
                ),
                TextField::create(
                    'InputElementId',
                    _t(static::class . '.INSTANT_SEARCH_INPUT_ELEMENT_ID', "The 'id' attribute of the search input")
                ),
                TextField::create(
                    'ContainerElementId',
                    _t(static::class . '.INSTANT_SEARCH_PARENT_ELEMENT_ID', "The 'id' attribute of the container element that will show the hits")
                )
            ]
        );
        return $fields;
    }

    /**
     * Perform validation if nodes are set
     */
    public function onBeforeWrite() {
        parent::onBeforeWrite();
        $this->getTypesenseNodes();
    }

    /**
     * Retrieve nodes for Typesense instantsearch usage
     */
    public function getTypesenseNodes(): array {
        $nodes = [];
        $searchNodes = preg_split("/[\n\r]+/", $this->Nodes);
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

    public function getCollectionName(): string {
        $collectionName = '';
        // select via relation first, if set
        $collection = $this->Collection();
        if($collection && $collection->isInDB()) {
            $collectionName = $collection->Name;
        }
        if(!$collectionName) {
            // a static name provided in the field overrules
            $collectionName = $this->getField('CollectionName');
        }
        return (string)$collectionName;
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'INSTANTSEARCH_CONFIG_VIEW' => [
                'name' => _t(static::class . '.PERMISSION_VIEW', 'View Typesense InstantSearch configuration'),
                'category' => 'Typesense InstantSearch',
            ],
            'INSTANTSEARCH_CONFIG_EDIT' => [
                'name' => _t(static::class . '.PERMISSION_EDIT', 'Edit Typesense InstantSearch configuration'),
                'category' => 'Typesense InstantSearch',
            ],
            'INSTANTSEARCH_CONFIG_CREATE' => [
                'name' => _t(static::class . '.PERMISSION_CREATE', 'Create Typesense InstantSearch configuration'),
                'category' => 'Typesense InstantSearch',
            ],
            'INSTANTSEARCH_CONFIG_DELETE' => [
                'name' => _t(static::class . '.PERMISSION_DELETE', 'Delete Typesense InstantSearch configuration'),
                'category' => 'Typesense InstantSearch',
            ]
        ];
    }

    /**
     * Given a model, provide the instantsearch interface for it
     * using the configuration set in this model
     * The model can provide overrides to the general config if required
     */
    public function provideInstantSearchFor(DataObject $model) {
        $collectionName = $model->getCollectionName();
        if($collectionName === '') {
            return null;
        }

        $id = $model->getTypesenseUniqID();
        $inputId = $this->InputElementId;
        if(!$inputId) {
            $inputId = $model->getTypesenseBindToInputId();
        }
        $parentId = $this->ContainerElementId;
        if(!$parentId) {
            $model->getTypesenseBindToParentId();
        }
        $apiKey = $this->SearchKey;
        try {
            $nodes = $this->getTypesenseNodes();
        } catch (\Exception $e) {
        }
        $queryBy = $this->QueryBy;
        if(!$queryBy) {
            $queryBy = 'Title';
        }
        $serverExtra = [
            'cacheSearchResultsForSeconds' => 120
        ];

        $data = [
            'c' => $this->ID,
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
        return InstantSearchService::provide($data);
    }
}

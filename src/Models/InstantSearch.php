<?php

namespace NSWDPC\Search\Typesense\Models;

use ElliotSawyer\SilverstripeTypesense\Collection;
use ElliotSawyer\SilverstripeTypesense\Typesense;
use NSWDPC\Search\Typesense\Services\InstantSearch as InstantSearchService;
use NSWDPC\Search\Typesense\Services\Logger;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\Filters\ExactMatchFilter;
use SilverStripe\ORM\Filters\PartialMatchFilter;
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
        'AriaLabel' => 'Varchar(255)',
        'SearchKey' => 'Varchar(255)',
        'SearchScope' => 'Text',
        'Nodes' => 'Text',
        'QueryBy' => 'Varchar(255)',
        'CollectionName' => 'Varchar(255)',
        'InputElementId' => 'Varchar(255)',
        'ContainerElementId' => 'Varchar(255)'
    ];

    private static array $has_one = [
        'Collection' => Collection::class
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'Enabled.Nice' => 'Enabled?'
    ];

    private static array $searchable_fields = [
        'Title' => PartialMatchFilter::class,
        'Enabled' => ExactMatchFilter::class,
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
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY', 'Search-only key')
                )->setDescription(
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY_WARNING', "Use a Typesense search-only API key with the single action 'documents:search'.")
                ),
                TextareaField::create(
                    'SearchScope',
                    _t(static::class . '.INSTANT_SEARCH_SEARCHSCOPE', 'Provide the search scope as JSON'),
                )->setDescription(
                    _t(static::class . '.INSTANT_SEARCH_SEARCHSCOPE_NOTES', 'See Typesense documentation for instructions on setting this value')
                )->setRows(10),
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
                    _t(static::class . '.INSTANT_SEARCH_QUERYBY', 'Fields to query. Separate fields by a comma')
                ),
                TextField::create(
                    'Prompt',
                    _t(static::class . '.INSTANT_SEARCH_PROMPT', 'Field prompt, optional')
                ),
                TextField::create(
                    'AriaLabel',
                    _t(static::class . '.INSTANT_SEARCH_ARIA_LABEL', 'Instructions for screen readers')
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
     * Validate the model
     */
    public function validate() {
        $result = parent::validate();

        // validate the key entered
        if($this->SearchKey) {
            $searchKey = $this->validateSearchKey($this->SearchKey);
            if($searchKey === '') {
                $this->SearchKey = '';// reset on invalid
                $result->addError(
                    _t(
                        static::class . ".SEARCH_KEY_INVALID",
                        "The search key provided is invalid. It must exist at the Typesense server and have a single action 'documents:search'"
                    )
                );
            }
        }


        if($this->SearchScope) {
            $searchScope = $this->validateSearchScope($this->SearchScope);
            if($searchScope === '') {
                $result->addError(
                    _t(
                        static::class . ".SEARCH_SCOPE_INVALID_JSON",
                        "The search scope provided is not valid JSON"
                    )
                );
            }
        }

        if(!$this->validateTypesenseNodes()) {
            $result->addError(
                _t(
                    static::class . ".SEARCH_INVALID_NODES",
                    "The server nodes are invalid. Each node should be a full URL with port."
                )
            );
        }

        return $result;
    }

    /**
     * Validate the search key provided
     */
    protected function validateSearchKey(string $searchKey): string {
        try {
            if($searchKey === '') {
                return '';
            }
            $client = Typesense::client();
            $results = $client->keys->retrieve();
            $keyFound = false;
            // print_r($results);
            foreach($results['keys'] as $key) {
                if(!str_starts_with($searchKey, $key['value_prefix'])) {
                    // ignore this key returned
                    continue;
                }

                $keyFound = true;
                // check the prefixed key's actions
                // scoped keys can only contain document:search
                // https://typesense.org/docs/28.0/api/api-keys.html#generate-scoped-search-key
                if(!isset($key['actions']) || $key['actions'] != ['documents:search']) {
                    throw new \InvalidArgumentException("Invalid key actions value");
                }
            }

            if(!$keyFound) {
                throw new \InvalidArgumentException("The key entered does not exist");
            }

        } catch (\Exception $e) {
            // on error clear the value
            $searchKey = '';
        }

        return $searchKey;
    }

    /**
     * Validate and return the search scope, if valid will pretty print the JSON value
     * back into SearchScope value
     */
    public function validateSearchScope(string $searchScope): string {
        $searchScope = trim($searchScope);
        if($searchScope !== '') {
            try {
                $scope = json_decode($searchScope, true, 512, JSON_THROW_ON_ERROR);
                if(is_array($scope)) {
                    return json_encode($scope, JSON_PRETTY_PRINT);
                }
            } catch (\JsonException $jsonException) {
                $searchScope = '';
            }
        } else {
            $searchScope = '';
        }
        return $searchScope;
    }

    public function validateTypesenseNodes() {
        try {
            $this->getTypesenseNodes();
            return true;
        } catch (\Exception $exception) {
            return false;
        }
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
     * Get a scoped search key, using the TYPESENSE_SEARCH_KEY or TYPESENSE_API_KEY if former not set
     */
    protected function getScopedSearchKey(): ?string {

        // prefer the stored key
        $searchKey = Environment::getEnv('TYPESENSE_SEARCH_KEY');
        if(!$searchKey) {
            // try the one entered in the UI
            $searchKey = $this->SearchKey;
        }

        // check if valid
        if(!$searchKey) {
            Logger::log("No Typesense search or API key defined - cannot create a scoped search key", "NOTICE");
            return null;
        }

        $searchKey = $this->validateSearchKey($searchKey);
        if($searchKey === '') {
            Logger::log("The search key in use is invalid. Please provide a search only key with a single action of 'documents:search'.", "NOTICE");
            return null;
        }

        $client = Typesense::client();
        $searchScope = trim($this->SearchScope ?? '');
        // ensure a default scope is set, if invalid
        $defaultScope = [
            'include_fields' => 'TypesenseSearchResultData'
        ];
        if($searchScope) {
            try {
                $scope = json_decode($searchScope, true, 512, JSON_THROW_ON_ERROR);
                if(!is_array($scope)) {
                    throw new \RuntimeException("Invalid JSON string");
                }
            } catch (\Exception $exception) {
                $scope = $defaultScope;
            }
        } else {
            $scope = $defaultScope;
        }

        $scopedKey = $client->keys->generateScopedSearchKey($searchKey, $scope);
        if(is_string($scopedKey)) {
            return $scopedKey;
        } else {
            return null;
        }
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

        $scopedApiKey = $this->getScopedSearchKey();
        if(!$scopedApiKey) {
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

        $prompt = $this->Prompt ?? '';
        $ariaLabel = $this->AriaLabel ?? '';

        $data = [
            'c' => $this->ID,
            'id' => $id,
            'inputId' => $inputId,
            'parentId' => $parentId,
            'apiKey' => $scopedApiKey,
            'queryBy' => $queryBy,
            'nodes' => $nodes,
            'collectionName' => $collectionName,
            'serverExtra' => $serverExtra,
            'placeholder' => $prompt,
            'ariaLabel' => $ariaLabel
        ];

        // Add instantsearch
        return InstantSearchService::provide($data);
    }
}

<?php

namespace NSWDPC\Search\Typesense\Models;

use ElliotSawyer\SilverstripeTypesense\Collection;
use NSWDPC\Search\Typesense\Services\InstantSearch as InstantSearchService;
use NSWDPC\Search\Typesense\Services\ScopedSearch;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\Filters\ExactMatchFilter;
use SilverStripe\ORM\Filters\PartialMatchFilter;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Configuration model for instantsearch
 * @property string $Title
 * @property bool $Enabled
 * @property ?string $Prompt
 * @property ?string $AriaLabel
 * @property ?string $Nodes
 * @property ?string $QueryBy
 * @property string $CollectionName
 * @property ?string $InputElementId
 * @property ?string $ContainerElementId
 * @property ?string $HitLinkField
 * @property ?string $HitTitleField
 * @property ?string $HitAbstractField
 * @property int $CollectionID
 * @method \ElliotSawyer\SilverstripeTypesense\Collection Collection()
 * @mixin \NSWDPC\Search\Typesense\Extensions\ScopedSearchExtension
 */
class InstantSearch extends DataObject implements PermissionProvider
{
    private static string $table_name = "TypesenseInstantSearch";

    private static string $singular_name = "InstantSearch configuration";

    private static string $plural_name = "InstantSearch configurations";

    private static array $db = [
        'Title' => 'Varchar(255)',
        'Enabled' => 'Boolean',
        'Prompt' => 'Varchar(255)',
        'AriaLabel' => 'Varchar(255)',
        'Nodes' => 'Text',
        'QueryBy' => 'Varchar(255)',
        'CollectionName' => 'Varchar(255)',
        'InputElementId' => 'Varchar(255)',
        'ContainerElementId' => 'Varchar(255)',
        'HitLinkField' => 'Varchar(255)',
        'HitTitleField' => 'Varchar(255)',
        'HitAbstractField' => 'Varchar(255)',
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

    #[\Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(array_merge(['CollectionID'], array_keys(static::config()->get('db'))));
        $fields->removeByName(['SearchKey','SearchScope']);
        $fields->addFieldsToTab(
            'Root.Main',
            [
                CompositeField::create(
                    TextField::create(
                        'Title',
                        _t(static::class . '.INSTANT_SEARCH_TITLE', 'Title, for internal use only')
                    )->setDescription(
                        _t(static::class . '.INSTANT_SEARCH_TITLE_NOTE', "This is used to describe the search for CMS editors, so that they can select the relevant configuration")
                    ),
                    CheckboxField::create(
                        'Enabled',
                        _t(static::class . '.USE_INSTANT_ENABLED', 'Enabled')
                    )->setDescription(
                        _t(static::class . '.INSTANT_SEARCH_ENABLED_NOTE', "Only enabled search configurations can be used on this website")
                    ),
                )->setTitle(
                    _t(static::class . '.INSTANT_SEARCH_GENERAL_DETAILS', 'General information')
                ),

                CompositeField::create(
                    TextareaField::create(
                        'Nodes',
                        _t(static::class . '.INSTANT_SEARCH_NODES', 'The server node(s), if different to the main configured Typesense server(s)'),
                    )->setDescription(
                        _t(static::class . '.INSTANT_SEARCH_NODES_NOTES', 'One node per line, include protocol host and port e.g. https://search1.example.com:1890')
                    )->setRows(3),
                    ScopedSearch::getSearchKeyField(),
                    ScopedSearch::getSearchScopeField(),
                    TextField::create(
                        'QueryBy',
                        _t(static::class . '.INSTANT_SEARCH_QUERYBY', 'Fields to query. Separate each field with a comma.')
                    )->setDescription(
                        _t(static::class . '.INSTANT_SEARCH_QUERYBY_NOTES', 'You can also add fields via query_by to the search scope field')
                    )
                )->setTitle(
                    _t(static::class . '.INSTANT_SEARCH_API_SERVER_DETAILS', 'API server details')
                ),

                CompositeField::create(
                    DropdownField::create(
                        'CollectionID',
                        _t(static::class . '.INSTANT_SEARCH_COLLECTION_SELECT', 'Choose a Collection to search'),
                        Collection::get()->filter(['Enabled' => 1])->sort(['Name' => 'ASC'])->map('ID', 'Name')
                    )->setEmptyString(''),
                    TextField::create(
                        'CollectionName',
                        _t(static::class . '.INSTANT_SEARCH_COLLECTION_NAME', '...or enter a collection name')
                    )
                )->setTitle(
                    _t(static::class . '.INSTANT_SEARCH_COLLECTION', 'Collection selection')
                ),

                CompositeField::create(
                    TextField::create(
                        'InputElementId',
                        _t(static::class . '.INSTANT_SEARCH_INPUT_ELEMENT_ID', "The 'id' attribute of the search field that should use this configuration.")
                    ),
                    TextField::create(
                        'Prompt',
                        _t(static::class . '.INSTANT_SEARCH_PROMPT', 'Field prompt, optional')
                    ),
                    TextField::create(
                        'AriaLabel',
                        _t(static::class . '.INSTANT_SEARCH_ARIA_LABEL', 'Instructions for screen readers')
                    )->setDescription(
                        _t(static::class . '.INSTANT_SEARCH_ARIA_LABEL_NOTES', 'This value will be added as the aria-label attribute on the search input')
                    ),
                )->setTitle(
                    _t(static::class . '.INSTANT_SEARCH_BOX_CONFIGURATION', 'Searchbox/form details')
                ),

                CompositeField::create(
                    TextField::create(
                        'ContainerElementId',
                        _t(static::class . '.INSTANT_SEARCH_PARENT_ELEMENT_ID', "The 'id' attribute of the container element that will hold the hits")
                    )->setDescription(
                        _t(static::class . '.INSTANT_SEARCH_PARENT_ELEMENT_ID_NOTES', 'The hitbox will be added to the end of this element')
                    ),
                    TextField::create(
                        'HitLinkField',
                        _t(static::class . '.INSTANT_SEARCH_HIT_LINK_PROPERTY', "The property on the 'hit' that holds the link to the result"),
                    ),
                    TextField::create(
                        'HitTitleField',
                        _t(static::class . '.INSTANT_SEARCH_HIT_TITLE_PROPERTY', "The property on the 'hit' that holds the title of the result")
                    ),
                    TextField::create(
                        'HitAbstractField',
                        _t(static::class . '.INSTANT_SEARCH_HIT_TITLE_PROPERTY', "The property on the 'hit' that holds the abstract of the result")
                    )
                )->setTitle(
                    _t(static::class . '.INSTANT_SEARCH_BOX_CONFIGURATION', 'Hitbox details')
                )
            ]
        );
        return $fields;
    }

    /**
     * Validate the model
     */
    #[\Override]
    public function validate()
    {
        $result = parent::validate();

        if (!$this->validateTypesenseNodes()) {
            $result->addError(
                _t(
                    static::class . ".SEARCH_INVALID_NODES",
                    "The server nodes are invalid. Each node should be a full URL with port."
                )
            );
        }

        return $result;
    }

    public function validateTypesenseNodes(): bool
    {
        try {
            $this->getTypesenseNodes();
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Retrieve nodes for Typesense instantsearch usage
     */
    public function getTypesenseNodes(): array
    {
        $nodes = [];
        $searchNodes = preg_split("/[\n\r]+/", $this->Nodes);
        if (is_array($searchNodes)) {
            foreach ($searchNodes as $searchNode) {
                $url = parse_url($searchNode);
                $scheme = $url['scheme'] ?? '';
                $host = $url['host'] ?? '';
                $port = $url['port'] ?? '';
                if (!$port) {
                    $port = ($scheme == "https" ? 443 : 80);
                }

                $path = $url['path'] ?? '';
                if (!isset($scheme)) {
                    throw ValidationException::create(_t(static::class . '.INSTANT_SEARCH_INVALID_URL_SCHEME', 'URL {url} does not include a scheme', ['url' => $searchNode]));
                }

                if (!isset($host)) {
                    throw ValidationException::create(_t(static::class . '.INSTANT_SEARCH_INVALID_URL_HOST', 'URL {url} does not include a host', ['url' => $searchNode]));
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

    public function getCollectionName(): string
    {
        $collectionName = '';
        // select via relation first, if set
        $collection = $this->Collection();
        if ($collection && $collection->isInDB()) {
            $collectionName = $collection->Name;
        }

        if (!$collectionName) {
            // a static name provided in the field overrules
            $collectionName = $this->getField('CollectionName');
        }

        return (string)$collectionName;
    }


    #[\Override]
    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'INSTANTSEARCH_CONFIG_EDIT');
    }

    #[\Override]
    public function canView($member = null)
    {
        return Permission::checkMember($member, 'INSTANTSEARCH_CONFIG_VIEW');
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'INSTANTSEARCH_CONFIG_CREATE');
    }

    #[\Override]
    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'INSTANTSEARCH_CONFIG_DELETE');
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
    public function provideInstantSearchFor(DataObject $model): ?\SilverStripe\ORM\FieldType\DBHTMLText
    {
        // @phpstan-ignore method.notFound (extension method provided by InstantSearchExtension)
        $collectionName = $model->getCollectionName();
        if ($collectionName === '') {
            return null;
        }

        /** getTypesenseScopedSearchKey provided by SearchScope data extension */
        $scopedApiKey = $this->getTypesenseScopedSearchKey();
        if (!$scopedApiKey) {
            return null;
        }

        // @phpstan-ignore method.notFound (extension method provided by InstantSearchExtension)
        $id = $model->getTypesenseUniqID();
        $inputId = $this->InputElementId;
        if (!$inputId) {
            // @phpstan-ignore method.notFound (extension method provided by InstantSearchExtension)
            $inputId = $model->getTypesenseBindToInputId();
        }

        $parentId = $this->ContainerElementId;
        if (!$parentId) {
            // @phpstan-ignore method.notFound (extension method provided by InstantSearchExtension)
            $model->getTypesenseBindToParentId();
        }

        try {
            $nodes = $this->getTypesenseNodes();
        } catch (\Exception) {
            $nodes = null; // If exception thrown, node is undefined
        }

        $queryBy = $this->QueryBy;
        if (!$queryBy) {
            $queryBy = 'Title';
        }

        $serverExtra = [
            'cacheSearchResultsForSeconds' => 120
        ];

        $prompt = $this->Prompt ?? '';
        $ariaLabel = $this->AriaLabel ?? '';

        $hitTemplate = null;
        $hitLinkField = $this->HitLinkField ?? '';
        $hitTitleField = $this->HitTitleField ?? '';
        if ($hitLinkField && $hitTitleField) {
            $hitTemplate = [
                'link' => $hitLinkField,
                'title' => $hitTitleField,
                'abstract' => $this->HitAbstractField ?? ''
            ];
        }

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
            'ariaLabel' => $ariaLabel,
            'hitTemplate' => $hitTemplate
        ];

        // Add instantsearch
        return InstantSearchService::provide($data);
    }

}

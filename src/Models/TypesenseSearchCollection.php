<?php

namespace NSWDPC\Search\Typesense\Models;

use KevinGroeger\CodeEditorField\Forms\CodeEditorField;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\TypesenseDocument;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * Represents a collection, with field metadata being pulled from YML configuration
 * @property ?string $Name
 * @property ?string $RecordClass
 * @property bool $Enabled
 * @property bool $IsCreatedFromConfig
 * @property ?string $Metadata
 */
class TypesenseSearchCollection extends DataObject implements PermissionProvider
{
    /**
     * Configured collections
     */
    private static array $collections = [];

    private static array $db = [
        'Name' => 'Varchar(255)',
        'RecordClass' => 'Varchar(255)',
        'Enabled' => 'Boolean',
        'IsCreatedFromConfig' => 'Boolean',
        'Metadata' => 'Text' // applicable metadata for the collection, including fields
    ];

    private static array $summary_fields = [
        'Name' => 'Name',
        'RecordClass' => 'Record type',
        'Enabled.Nice' => 'Enabled?',
        'IsCreatedFromConfig.Nice' => 'From configuration?'
    ];

    private static array $indexes = [
        'Name' =>  [
            'columns' => ['Name'],
            'type' => 'unique' // the collection name is unique
        ],
        'RecordClass' => true,
        'Enabled' => true
    ];

    private static string $table_name = 'TypesenseCollection';

    private static string $singular_name = 'Collection';

    private static string $plural_names = 'Collections';

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'TYPESENSE_COLLECTION_VIEW' => [
                'name' => _t(static::class . '.PERMISSION_VIEW', 'View Typesense collections'),
                'category' => 'Typesense InstantSearch',
            ],
            'TYPESENSE_COLLECTION_EDIT' => [
                'name' => _t(static::class . '.PERMISSION_EDIT', 'Edit Typesense collections'),
                'category' => 'Typesense InstantSearch',
            ],
            'TYPESENSE_COLLECTION_CREATE' => [
                'name' => _t(static::class . '.PERMISSION_CREATE', 'Create Typesense collections'),
                'category' => 'Typesense InstantSearch',
            ],
            'TYPESENSE_COLLECTION_DELETE' => [
                'name' => _t(static::class . '.PERMISSION_DELETE', 'Delete Typesense collections'),
                'category' => 'Typesense InstantSearch',
            ],
            'TYPESENSE_COLLECTION_REINDEX' => [
                'name' => _t(static::class . '.PERMISSION_REINDEX', 'Reindex Typesense collections'),
                'category' => 'Typesense InstantSearch',
            ],
        ];
    }

    #[\Override]
    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'TYPESENSE_COLLECTION_EDIT');
    }

    #[\Override]
    public function canView($member = null)
    {
        return Permission::checkMember($member, 'TYPESENSE_COLLECTION_VIEW');
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'TYPESENSE_COLLECTION_CREATE');
    }

    #[\Override]
    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'TYPESENSE_COLLECTION_DELETE');
    }

    /**
     * Helper function for when ->Title is called
     */
    #[\Override]
    public function getTitle(): ?string
    {
        return $this->Name;
    }

    #[\Override]
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $collections = static::getConfiguredCollections();
        foreach ($collections as $recordClass => $collectionData) {
            try {
                $collectionName = trim((string) ($collectionData['name'] ?? null));
                if ($collectionName === '') {
                    throw new \RuntimeException("'name' value is empty");
                }

                $collection = static::findOrCreate($collectionName, $recordClass, $collectionData, true);
                if ($collection->isInDB()) {
                    DB::alteration_message("Local collection '{$collection->Name}' found/created", "info");
                } else {
                    DB::alteration_message("Local collection for '{$recordClass}' not found or created", "error");
                }
            } catch (\Exception $exception) {
                DB::alteration_message("Local collection error on find/create {$exception->getMessage()}", "error");
            }
        }
    }

    #[\Override]
    public function getCmsFields()
    {
        $fields = parent::getCmsFields();

        $metadataField = CodeEditorField::create(
            'Metadata',
            _t(self::class . ".COLLECTION_METADATA", "Collection metadata")
        )->setMode('ace/mode/json')
        ->setTheme('ace/theme/dracula')
        ->setHeight(640);

        $fields->addFieldsToTab(
            'Root.Main',
            [
                CheckboxField::create(
                    'Enabled',
                    _t(self::class . ".COLLECTION_ENABLED", "Collection enabled")
                ),
                $metadataField
            ]
        );
        $readonlyFields = ['Name','RecordClass','IsCreatedFromConfig'];
        if ($this->IsCreatedFromConfig) {
            // TODO: should be stopped from editing?
            // $readonlyFields[] = 'Metadata';
        }

        $fields->makeFieldReadonly($readonlyFields);

        return $fields;
    }

    public function getPrettyMetadata(): ?string
    {
        $metadata = $this->Metadata;
        if (is_null($metadata)) {
            return null;
        } elseif (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            return json_encode($decoded, JSON_PRETTY_PRINT);
        } else {
            return null;
        }
    }

    #[\Override]
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        try {
            $name = $this->getCollectionName();
            if ($name !== '') {
                $this->Name = $name;
            }

            // prettify the configuration stored for viewing
            // TODO can this be done on the display side?
            $this->Metadata = $this->getPrettyMetadata();
        } catch (\Exception) {
            // noop
        }
    }

    /**
     * Validate the record
     */
    #[\Override]
    public function validate()
    {
        $valid = parent::validate();
        if ($this->isInDB()) {

            $exists = $this->collectionExists($this->Name ?? '', true);
            if ($exists) {
                $valid->addFieldError(
                    'Name',
                    _t(
                        self::class . '.COLLECTION_NAME_EXISTS',
                        'A collection with this name already exists'
                    )
                );
            }

            try {
                $metadata = $this->validateMetadata();
            } catch (\Exception) {
                $valid->addFieldError(
                    'Metadata',
                    _t(
                        self::class . '.METADATA_INVALID',
                        'The provided collection metadata is invalid'
                    )
                );
            }

            $recordClass = $this->getValidRecordClass($valid);
        } else {
            $exists = $this->collectionExists($this->Name ?? '', false);
            if ($exists) {
                $valid->addFieldError(
                    'Name',
                    _t(
                        self::class . '.COLLECTION_NAME_EXISTS',
                        'A collection with this name already exists'
                    )
                );
            }
        }

        return $valid;
    }

    public function collectionExists(string $name, bool $excludeCurrent = false): bool
    {
        if ($name === '') {
            return false;
        }

        $list = static::get()->filter(['Name' => $name]);
        if ($excludeCurrent) {
            $list = $list->exclude(['ID' => $this->ID]);
        }

        $collection = $list->first();
        return $collection && $collection->isInDB();
    }

    public function getValidRecordClass(?\SilverStripe\ORM\ValidationResult &$valid = null): string
    {
        $isValid = false;
        $recordClass = trim($this->RecordClass ?? '');
        if ($recordClass === '') {
            if (!is_null($valid)) {
                $valid->addFieldError(
                    'RecordClass',
                    _t(
                        self::class . '.COLLECTION_VALIDATE_EMPTY_CLASS',
                        'Please provide a linked class name'
                    )
                );
            }
        } elseif (!class_exists($recordClass)) {
            if (!is_null($valid)) {
                $valid->addFieldError(
                    'RecordClass',
                    _t(
                        self::class . '.COLLECTION_VALIDATE_CLASS_NOT_EXIST',
                        'The class {recordClass} does not exist',
                        [
                            'recordClass' => $recordClass
                        ]
                    )
                );
            }
        } elseif (!is_subclass_of($recordClass, DataObject::class)) {
            if (!is_null($valid)) {
                $valid->addFieldError(
                    'RecordClass',
                    _t(
                        self::class . '.COLLECTION_VALIDATE_CLASS_NOT_DATAOBJECT',
                        'The class {recordClass} is not a DataObject, provide a class that extends DataObject',
                        [
                            'recordClass' => $recordClass
                        ]
                    )
                );
            }
        } else {
            $isValid = true;
        }

        if ($isValid) {
            return $recordClass;
        } else {
            return '';
        }
    }

    /**
     * Find or create a collection record locally in the database
     * @param string $name the name of the collection
     * @param string $recordClass the classname linked to the collection's data, must be a subclass of DataObject
     * @param array $metadata collection metadata
     * @param bool $isCreatedFromConfig when a record is created from configuration, store this value
     *
     */
    public static function findOrCreate(string $name, string $recordClass, array $metadata, bool $isCreatedFromConfig = false): static
    {
        // Name is the unique index
        $collection = static::get()->filter([
            'Name' => $name
        ])->first();
        if (!$collection) {
            // Create a new, enabled, collection
            $collection = static::create([
                'Name' => $name,
                'RecordClass' => $recordClass,
                'Enabled' => true
            ]);
        }

        if (is_null($collection->Metadata) || $collection->Metadata === '') {
            $collection->Metadata = trim(json_encode($metadata));
        }

        $collection->ClassName = static::class;// ensure class is updated
        if ($isCreatedFromConfig) {
            // ensure this is set when true
            $collection->IsCreatedFromConfig = true;
        }

        $id = $collection->write();
        if (!$id) {
            throw new \RuntimeException("Failed to write collection record locally");
        }

        return $collection;

    }

    /**
     * Return all the fields in the array
     */
    public function getCollectionFields(): array
    {
        try {
            $metadata = $this->getMetadataAsArray();
            return isset($metadata['fields']) && is_array($metadata['fields']) ? $metadata['fields'] : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * Return all the fields in the array that can be used in a search
     * See: https://typesense.org/docs/29.0/api/search.html#query-parameters (query_by)
     * @todo nested object, object[] search
     */
    public function getCollectionFieldsForQueryBy(): array
    {
        try {
            $fieldsForSearch = [];
            $fields = $this->getCollectionFields();
            foreach ($fields as $field) {
                // field must be string and must be index-ed (see 'Declaring a field as un-indexed' in collection docs)
                if (in_array($field['type'], ['string','string[]'])
                    // index is true by default, it can be not set, or set and true
                    && (!isset($field['index']) || (isset($field['index']) && $field['index']))
                ) {
                    $fieldsForSearch[] = $field['name'];
                }
            }

            return array_unique($fieldsForSearch);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Return all the fields in the array
     */
    public function getCollectionName(): string
    {
        try {
            $metadata = $this->getMetadataAsArray();
            return isset($metadata['name']) && is_string($metadata['name']) ? $metadata['name'] : '';
        } catch (\JsonException) {
            return '';
        }
    }

    /**
     * Return all the configured metadata for this collection
     */
    public function getMetadataAsArray(): array
    {
        try {
            $metadata = json_decode((string) $this->Metadata, true, 512, JSON_THROW_ON_ERROR);
            return is_array($metadata) ? $metadata : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * Validate configured metadata
     * @throws \OutOfRangeException|\SilverStripe\ORM\ValidationException
     * @return array the valid metadata
     */
    public function validateMetadata(): array
    {
        $metadata = $this->getMetadataAsArray();

        // required field based on documentation
        $required = ['name','fields'];
        foreach ($required as $requiredField) {
            if (!isset($metadata[$requiredField])) {
                throw \SilverStripe\ORM\ValidationException::create(
                    _t(
                        static::class . ' .VALIDATE_METADATA_MISSING_REQUIRED_FIELD',
                        "The metadata entry '{requiredField}' is required",
                        [
                            'requiredField' => $requiredField
                        ]
                    )
                );
            }
        }

        // Validate fields
        $fields = [
            'name' => [
                // required
                'validate' => fn ($value): bool => is_string($value) && $value !== '',
            ],
            'fields' => [
                // required
                'validate' => fn ($value): bool => is_array($value) && $value !== [],
            ],
            'token_separators' => [
                'validate' => is_string(...),
            ],
            'symbols_to_index' => [
                'validate' => is_string(...),
            ],
            'default_sorting_field' => [
                // TODO validate the field type, currently up to configuration to have this correct
                'validate' => is_string(...),
            ]
        ];

        foreach ($metadata as $key => $value) {

            if (!isset($fields[$key])) {
                throw new \OutOfRangeException("Unexpected metadata key '{$key}'");
            }

            // @phpstan-ignore function.alreadyNarrowedType
            if (is_callable($fields[$key]['validate'])) {
                $result = $fields[$key]['validate']($value);
                if ($result === false) {
                    throw \SilverStripe\ORM\ValidationException::create(
                        _t(
                            static::class . ' .VALIDATE_METADATA_INVALID_FIELD_VALUE',
                            "The metadata entry '{key}' is not valid",
                            [
                                'key' => $key
                            ]
                        )
                    );
                }
            }
        }

        // all valid
        return $metadata;
    }

    public static function getConfiguredCollections(): array
    {
        $collections = static::config()->get('collections') ?? [];
        if (!is_array($collections)) {
            return [];
        } else {
            return $collections;
        }
    }

    /**
     * Import this collection to the server
     */
    public function import(int $limit = 100, array $sort = ['ID' => 'ASC'], bool $verbose = false): int
    {
        $batchCount = 0;
        $total = 0;
        $start = 0;
        if ($verbose) {
            DB::alteration_message("Start import limit={$limit}", "changed");
        }

        do {
            $batchCount = $this->batchedImport($sort, $limit, $start);
            $total += $batchCount;
            $start += $limit;
            if ($verbose) {
                DB::alteration_message("Batch count={$batchCount} of total={$total}", "changed");
            }
        } while ($batchCount > 0);

        if ($verbose) {
            DB::alteration_message("Import done total={$total}", "changed");
        }

        return $total;
    }

    /**
     * This batched import method is taken from a PR created at https://codeberg.org/codemdev/silverstripe-typesense/src/branch/aggregated-updates/src/Models/Collection.php#L438
     *
     * Batched load documents into Typesense, the batching is controlled by an external process
     *
     * @param array $sort batch sorting
     * @param int $limit the number of records in the batch
     * @param int $start the start of the batch
     * @return int the number of records found based on the args, 0 if none
     */
    public function batchedImport(array $sort, int $limit = 0, $start = 0): int
    {

        // Check total record count
        $recordsCount = $this->getRecordsCount($sort);
        if ($recordsCount === 0) {
            Logger::log(
                _t(
                    static::class . ' .BATCHEDIMPORT_NO_DOCUMENTS_FOUND',
                    'No documents found'
                ),
                "INFO"
            );
        }

        // get records based on args
        $records = $this->getRecords($sort)->limit($limit, $start);
        $batchCount = $records->count();
        if ($batchCount === 0) {
            // no more records
            Logger::log(
                _t(
                    static::class . ' .BATCHEDIMPORT_NO_MORE_DOCUMENTS_FOUND',
                    'No more documents found'
                ),
                "INFO"
            );
            return 0;
        }

        $docs = [];
        $collectionFields = $this->getCollectionFields();
        $collectionName = $this->getCollectionName();
        foreach ($records as $record) {

            $data = [];
            if ($record->hasMethod('getTypesenseDocument')) {
                // See DocumentDataExtension
                $data = $record->getTypesenseDocument($collectionFields);
            } else {
                // Try to get the document directly
                $data = TypesenseDocument::get($record, $collectionFields);
            }

            if (is_array($data) && $data !== []) {
                $docs[] = $data;
            } else {
                Logger::log(
                    _t(
                        static::class. ' .BATCHEDIMPORT_SKIP_RECORD',
                        'Batch import: skip record #{id} of class {className} as empty data',
                        [
                            'id' => $record->ID,
                            'className' => $record->ClassName
                        ]
                    ),
                    "NOTICE"
                );
            }
        }

        Logger::log(
            _t(
                static::class .'.BATCHEDIMPORT_IMPORTING_STEP',
                "Batch importing {count} from offset {start} into '{name}'",
                [
                    'count' => count($docs),
                    'start' => $start,
                    'name' => $collectionName
                ]
            ),
            "INFO"
        );

        $manager = Injector::inst()->get(ClientManager::class);
        $client = $manager->getConfiguredClient();
        $options = [
            'action' => 'upsert', // importing the whole document
            'return_id' => true // return ids
        ];
        $result = $client->collections[$collectionName]->documents->import($docs, $options);
        if (is_string($result)) {
            $result = json_decode($result, true);
        }

        $success = 0;
        $error = 0;
        if (is_array($result)) {
            foreach ($result as $resultItem) {
                if ($resultItem['success']) {
                    $success++;
                } else {
                    $errorMsg = $resultItem['error'] ?? '(not set)';
                    Logger::log(
                        _t(
                            static::class . ' .BATCHEDIMPORT_IMPORT_RECORD_ERROR',
                            "Batch import into '{name}' id={id} error={errorMsg}",
                            [
                                "name" => $collectionName,
                                "id" => $resultItem['id'],
                                "errorMsg" => $errorMsg
                            ]
                        ),
                        "NOTICE"
                    );
                    $error++;
                }
            }
        }

        Logger::log(
            _t(
                static::class . ' .BATCHEDIMPORT_IMPORTED_STEP_COMPLETE',
                'Batch importing result success={success} errors={error}',
                [
                    'success' => $success,
                    'error' => $error
                ]
            ),
            "INFO"
        );
        return $batchCount;
    }

    /**
     * Return the total number of local records for indexing
     *
     * @param array $sort sorting options
     */
    protected function getRecordsCount(array $sort = []): int
    {
        return $this->getRecords($sort)->count();
    }

    /**
     * Get the local records that can be indexed
     *
     * @param array $sort sorting option on the record list
     */
    protected function getRecords(array $sort = []): ?\SilverStripe\ORM\DataList
    {
        $recordClass = $this->getValidRecordClass();
        if ($recordClass === '') {
            throw \SilverStripe\ORM\ValidationException::create(
                _t(
                    static::class . ' .GETRECORDS_INVALID_LINKED_CLASS',
                    'The linked class is invalid'
                )
            );
        }

        // Get via versioned module and prefer the LIVE stage record if supported by the record
        $records = Versioned::withVersionedMode(
            function () use ($recordClass) {
                Versioned::set_stage(Versioned::LIVE);
                return $recordClass::get();
            }
        );

        $inst = Injector::inst()->get($recordClass);

        // Exclude records that have the 'ShowInSearch' field e.g. SiteTree, File
        if ($inst->hasField('ShowInSearch')) {
            $records = $records->exclude(['ShowInSearch' => 0]);
        }

        // Sort the list
        if ($sort !== []) {
            $records = $records->sort($sort);
        }

        // allow the instance to further process the records
        $inst->extend('onGetRecordsForTypesenseIndexing', $records);

        return $records;
    }

    /**
     * Create this collection at the server
     * @param array $clientOptions an array of options to pass to the TypesenseClient
     * @param array $createOptions an array of options to pass to collections->create()
     * @throws \Typesense\Exceptions\TypesenseClientError|\Http\Client\Exception
     */
    public function createAtServer(array $clientOptions = [], array $createOptions = []): bool
    {
        $manager = Injector::inst()->get(ClientManager::class);
        $client = $manager->getConfiguredClient($clientOptions);
        $metadata = $this->validateMetadata();
        if ($metadata !== []) {
            // this will throw an \Typesense\Exceptions\ObjectAlreadyExists if the collection already exists
            $client->collections->create($metadata, $createOptions);
        }

        // created
        return true;
    }

    /**
     * Delete this collection from the server
     * @throws \RuntimeException|\Typesense\Exceptions\TypesenseClientError|\Http\Client\Exception
     */
    public function deleteFromServer(array $clientOptions = []): bool
    {
        try {
            $name = $this->getCollectionName();
            if ($name === '') {
                throw new \RuntimeException("Cannot delete a collection with an empty name");
            }

            $manager = Injector::inst()->get(ClientManager::class);
            $client = $manager->getConfiguredClient($clientOptions);
            $client->collections[$name]->delete();
            return true;
        } catch (\Exception) {
            Logger::log(
                _t(
                    static::class . ' .COLLECTION_DELETE_FAIL',
                    'Collection {name} failed to delete',
                    [
                        'name' => $name
                    ]
                ),
                "NOTICE"
            );
            return false;
        }

    }

    /**
     * Return CMS actions for this record
     */
    #[\Override]
    public function getCMSActions()
    {
        $actions = parent::getCMSActions();
        $member = Security::getCurrentUser();
        if ($this->isInDB() && Permission::checkMember($member, 'TYPESENSE_COLLECTION_REINDEX')) {
            $refreshAction = FormAction::create(
                'doCollectionSync',
                _t(
                    self::class . '.START_COLLECTION_SYNC',
                    'Refresh the search index'
                )
            )->addExtraClass('btn-outline-primary font-icon-sync')
            ->setUseButtonTag(true);
            $actions->push($refreshAction);
        }

        return $actions;
    }


}

<?php

namespace NSWDPC\Search\Typesense\Models;

use KevinGroeger\CodeEditorField\Forms\CodeEditorField;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\TypesenseDocument;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Represents a collection, with field metadata being pulled from YML configuration
 */
class TypesenseSearchCollection extends DataObject
{

    /**
     * Configured collections
     */
    private static array $collections = [];

    /**
     * Field to allow administration of the collection within the admin interface
     */
    private static array $db = [
        'Name' => 'Varchar(255)',
        'LinkedClass' => 'Text',
        'Enabled' => 'Boolean',
        'Metadata' => 'Text' // applicable metadata for the collection, including fields
    ];

    /**
     * Add indexes to fields that are queried, avoid full table scans
     */
    private static array $indexes = [
        'Name' => true,
        'Enabled' => true
    ];

    private static string $table_name = 'TypesenseCollection';

    private static string $singular_name = 'Typesense search collection';

    private static string $plural_names = 'Typesense search collections';

    /**
     * Helper function for when ->Title is called
     */
    public function getTitle(): ?string
    {
        return $this->Name;
    }

    public function getCmsFields()
    {
        $fields = parent::getCmsFields();
        $fields->makeFieldReadonly(['Name','LinkedClass']);
        $fields->addFieldsToTab(
            'Root.Main',
            [
                CheckboxField::create(
                    'Enabled',
                    _t(self::class . ".COLLECTION_ENABLED", "Collection enabled")
                ),
                CodeEditorField::create(
                    'Metadata',
                    _t(self::class . ".COLLECTION_METADATA", "Collection metadata")
                )->setMode('ace/mode/json')
                ->setTheme('ace/theme/dracula')
            ]
        );
        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        try {
            $name = $this->getCollectionName();
            if($name !== '') {
                $this->Name = $name;
            }
        } catch (\Exception $exception) {
            // noop
        }
    }

    /**
     * Validate the record
     */
    public function validate()
    {
        $valid = parent::validate();
        if($this->isInDB()) {

            try {
                $metadata = $this->validateMetadata();
            } catch (\Exception $exception) {
                $valid->addFieldError(
                    'Metadata',
                    _t(
                        self::class . '.METADATA_INVALID',
                        'The provided collection metadata is invalid'
                    )
                );
            }
            $linkedClass = $this->getValidLinkedClass($valid);
        }
        return $valid;
    }

    public function getValidLinkedClass(?\SilverStripe\ORM\ValidationResult &$valid = null): string
    {
        $isValid = false;
        $linkedClass = trim($this->LinkedClass ?? '');
        if($linkedClass == '') {
            if(!is_null($valid)) {
                $valid->addFieldError(
                    'LinkedClass',
                    _t(
                        self::class . '.COLLECTION_VALIDATE_EMPTY_CLASS',
                        'Please provide a linked class name'
                    )
                );
            }
        } else if (!class_exists($linkedClass)) {
            if(!is_null($valid)) {
                $valid->addFieldError(
                    'LinkedClass',
                    _t(
                        self::class . '.COLLECTION_VALIDATE_CLASS_NOT_EXIST',
                        'The class {linkedClass} does not exist',
                        [
                            'linkedClass' => $linkedClass
                        ]
                    )
                );
            }
        } else if(!is_subclass_of($linkedClass, DataObject::class)) {
            if(!is_null($valid)) {
                $valid->addFieldError(
                    'LinkedClass',
                    _t(
                        self::class . '.COLLECTION_VALIDATE_CLASS_NOT_DATAOBJECT',
                        'The class {linkedClass} is not a DataObject, provide a class that extends DataObject',
                        [
                            'linkedClass' => $linkedClass
                        ]
                    )
                );
            }
        } else {
            $isValid = true;
        }

        if($isValid) {
            return $linkedClass;
        } else {
            return '';
        }
    }

    /**
     * Find or create a collection record locally in the database
     * @param string $name the name of the collection
     * @param string $linkedClass the classname linked to the collection's data, must be a subclass of DataObject
     * @param array $metadata collection metadata
     * 
     */
    public static function find_or_make(string $name, string $linkedClass, array $metadata): static
    {
        $collection = static::get()->filter([
            'Name' => $name,
            'LinkedClass' => $linkedClass
        ])->first();
        if(!$collection) {
            // Create a new, enabled, collection
            $collection = static::create([
                'Name' => $name,
                'LinkedClass' => $linkedClass,
                'Enabled' => true
            ]);
        }
        $collection->Metadata = json_encode($metadata);
        $id = $collection->write();
        if(!$id) {
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
        } catch (\JsonException $jsonException) {
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
            foreach($fields as $field) {
                // field must be string and must be index-ed (see 'Declaring a field as un-indexed' in collection docs)
                if(in_array($field->type, ['string','string[]']) && $field->index) {
                    $fieldsForSearch[] = $field->name;
                }
            }
            return array_unique($fieldsForSearch);
        } catch (\Exception $exception) {
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
        } catch (\JsonException $jsonException) {
            return '';
        }
    }

    /**
     * Return all the configured metadata for this collection
     */
    public function getMetadataAsArray(): array
    {
        try {
            $metadata = json_decode($this->Metadata, true, 512, JSON_THROW_ON_ERROR);
            return is_array($metadata) ? $metadata : [];
        } catch (\JsonException $jsonException) {
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
        foreach($required as $requiredField) {
            if(!isset($metadata[$requiredField])) {
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
                'validate' => function($value) { return is_string($value) && $value !== ''; },
            ],
            'fields' => [
                // required
                'validate' => function($value) { return is_array($value) && $value !== []; },
            ],
            'token_separators' => [
                'validate' => function($value) { return is_string($value); },
            ],
            'symbols_to_index' => [
                'validate' => function($value) { return is_string($value); },
            ],
            'default_sorting_field' => [
                // TODO validate the field type, currently up to configuration to have this correct
                'validate' => function($value) { return is_string($value); },
            ]
        ];

        foreach($metadata as $key => $value) {

            if(!isset($fields[$key])) {
                throw new \OutOfRangeException("Unexpected metadata key '{$key}'");
            }

            // @phpstan-ignore function.alreadyNarrowedType
            if(is_callable($fields[$key]['validate'])) {
                $result = $fields[$key]['validate']($value);
                if($result == false) {
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

    /**
     * Import this collection to the server
     */
    public function import(int $limit = 100, array $sort = ['ID' => 'ASC']): int
    {
        $batchCount = 0;
        $total = 0;
        do {
            $batchCount = $this->batchedImport($sort, $limit, 0);
            $total += $batchCount;
        } while($batchCount > 0);

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
        if($batchCount == 0) {
            // no more records
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
                'Batch importing {count} from offset {start} into \'{name}\'',
                [
                    'count'=> count($docs),
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
        if(is_string($result)) {
            $result = json_decode($result, true);
        }

        $success = 0;
        $error = 0;
        if(is_array($result)) {
            foreach($result as $resultItem) {
                if($resultItem['success']) {
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
                    'success'=> $success,
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
        $linkedClass = $this->getValidLinkedClass();
        if($linkedClass === '') {
            throw \SilverStripe\ORM\ValidationException::create(
                _t(
                    static::class . ' .GETRECORDS_INVALID_LINKED_CLASS',
                    'The linked class is invalid'
                )
            );
        }

        // Get via versioned module and prefer the LIVE stage record if supported by the record
        $records = Versioned::withVersionedMode(
            function() use ($linkedClass) {
                Versioned::set_stage(Versioned::LIVE);
                return $linkedClass::get();   
            }
        );

        $inst = Injector::inst()->get($linkedClass);

        // Exclude records that have the 'ShowInSearch' field e.g. SiteTree, File
        if ($inst->hasField('ShowInSearch')) {
            $records = $records->exclude(['ShowInSearch' => 0]);
        }

        // Sort the list
        if($sort !== []) {
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
        if($metadata !== []) {
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
            if($name === '') {
                throw new \RuntimeException("Cannot delete a collection with an empty name");
            }
            $manager = Injector::inst()->get(ClientManager::class);
            $client = $manager->getConfiguredClient($clientOptions);
            $client->collections[$name]->delete();
            return true;
        } catch (\Exception $exception) {
            Logger::log(
                _t(
                    static::class . ' .COLLECTION_DELETE_FAIL',
                    'Collection {name} failed to delete',
                    [
                        'name'=> $name
                    ]
                ),
                "NOTICE"
            );
            return false;
        }

    }

}
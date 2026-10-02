<?php

namespace NSWDPC\Search\Typesense\Models;

use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\ScopedSearch;
use NSWDPC\Typesense\CMS\Models\TypesenseSearchPage;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Represents a key that is validated to only have the relevant search only permission
 */
class TypesenseSearchOnlyKey extends DataObject implements PermissionProvider
{

    private static array $indexes = [
        'IsEnabled' => true
    ];

    private static string $table_name = 'TypesenseSearchOnlyKey';

    private static string $singular_name = 'Search-only key';

    private static string $plural_name = 'Search-only keys';


    private static array $db = [
        'Title' => 'Varchar(255)',// internal title of key
        'KeyVal' => 'Varchar(255)',
        'IsEnabled' => 'Boolean',
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'MaskedKeyVal' => 'Key identifier',
        'IsEnabled.Nice' => 'Enabled?'
    ];

    /**
     * Return identifier for menu selections
     */
    public function TitleWithMaskedKey(): string {
        return trim(($this->Title ?? '') . " - (" . $this->getMaskedKeyVal() . ")");
    }

    /**
     * Return the last N chars of the key
     */
    public function getMaskedKeyVal(): string
    {
        $val = $this->getField('KeyVal');
        if(!is_string($val)) {
            $val = '';
        }
        $val = substr($val, 0, 4);
        return $val;
    }

    public function setKeyValInput(?string $keyVal = null) {
        if($keyVal) {
            $this->KeyVal = $keyVal;
        }
    }

    public function getCmsFields() {
        $fields = parent::getCmsFields();
        $fields->removeByName(['KeyVal']);
        $fields->addFieldsToTab(
            'Root.Main', [
                TextField::create(
                    'Title',
                    _t(static::class . '.SEARCHONLY_KEY_TITLE', 'Name of the key'),
                ),
                TextField::create(
                    'KeyValInput',
                    _t(static::class . '.SEARCHONLY_KEY_VALUE', 'Value of the key'),
                    ''
                )->setRightTitle(
                    _t(
                        static::class . '.SEARCHONLY_KEY_MASKED_VAL',
                        'Current key: {masked}',
                        [
                            'masked' => $this->getMaskedKeyVal()
                        ]
                    )
                ),
                CheckboxField::create(
                    'IsEnabled',
                    _t(static::class . '.SEARCHONLY_KEY_ENABLED', 'Enabled?')
                )->setDescription(
                    _t(static::class . '.SEARCHONLY_KEY_ENABLED_HELP', 'Enable a key to make is selectable in the administration areas where keys can be selected')
                )
            ]
        );
        return $fields;
    }

    /**
     * Validate record
     */
    public function validate() {
        $valid = parent::validate();

        $keyVal = $this->KeyVal;
        if(is_string($keyVal)) {
            $keyVal = trim($keyVal);
            if($keyVal !== '') {
                $result = ScopedSearch::validateSearchOnlyKey($keyVal);
                if(!$result) {
                    $this->KeyVal = '';
                    $valid->addFieldError(
                        'KeyVal',
                        _t(
                            self::class . '.INVALID_SEARCH_ONLY_KEY',
                            'The key entered is not a valid search-only key. It must exist at the Typesense server and only have the assigned actions: {actions}',
                            [
                                'actions' => 'documents:search'
                            ]
                        )
                    );
                }
            }
        }

        return $valid;
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'TYPESENSE_KEY_VIEW' => [
                'name' => _t(static::class . '.PERMISSION_VIEW', 'View Typesense search only keys'),
                'category' => 'Typesense keys',
            ],
            'TYPESENSE_KEY_EDIT' => [
                'name' => _t(static::class . '.PERMISSION_EDIT', 'Edit Typesense search only keys'),
                'category' => 'Typesense keys',
            ],
            'TYPESENSE_KEY_CREATE' => [
                'name' => _t(static::class . '.PERMISSION_CREATE', 'Create Typesense search only keys'),
                'category' => 'Typesense keys',
            ],
            'TYPESENSE_KEY_DELETE' => [
                'name' => _t(static::class . '.PERMISSION_DELETE', 'Delete Typesense search only keys'),
                'category' => 'Typesense keys',
            ]
        ];
    }

    #[\Override]
    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'TYPESENSE_KEY_EDIT');
    }

    #[\Override]
    public function canView($member = null)
    {
        return Permission::checkMember($member, 'TYPESENSE_KEY_VIEW');
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'TYPESENSE_KEY_CREATE');
    }

    #[\Override]
    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'TYPESENSE_KEY_DELETE');
    }

    /**
     * Migrate a key
     * This is done outside the ORM as the key might fail validation as
     * it may not be a search only key but it still needs to be migrated.
     * Subsequent writes of the key will validate and the user can fix.
     */
    private function migrateKey(string $searchKey, string $label): int {
        try {
            DB::prepared_query(
                'INSERT INTO "TypesenseSearchOnlyKey" ("Title", "KeyVal") Values (?, ?)',
                [
                    $label,
                    $searchKey
                ]
            );
            $id = DB::get_generated_id('TypesenseSearchOnlyKey');
            return is_int($id) ? $id : 0;
        } catch (\Exception $exception) {
            Logger::log("Failed to migrate key: {$exception->getMessage()}", "NOTICE");
            return 0;
        }
    }

    public function requireDefaultRecords() {
        parent::requireDefaultRecords();

        // ScopedSearchExtension handling - get all core classes using it
        $knownClasses = [];
        $knownClasses[] = InstantSearch::class;
        if(\class_exists(TypesenseSearchPage::class)) {
            $knownClasses[] = TypesenseSearchPage::class;
        }

        $changes = 0;
        foreach($knownClasses as $knownClass) {

            // table for class
            $tableName = DataObject::getSchema()->tableName($knownClass);

            // get human label
            $classLabel = Config::inst()->get($knownClass, 'singular_name');
            if(!$classLabel) {
                $classLabel = ClassInfo::shortName($knownClass);
            }
            // update all known models
            $result = DB::prepared_query(
                'SELECT "ID", "SearchKey" FROM "' . Convert::raw2sql($tableName) . '" WHERE "SearchKey" IS NOT NULL AND "SearchKey" <> \'\'',
                []
            );
            foreach ($result as $record) {

                $label = "Automigrated key from {$classLabel} #{$record['ID']}";
                $keyId = $this->migrateKey($record['SearchKey'], $label);

                // update the source table if success
                if($keyId > 0) {
                    DB::alteration_message("Migrating {$knownClass} SearchKey #" . $record['ID'], "changed");
                    // remove the key val to avoid re-migrations
                    DB::prepared_query(
                        'UPDATE "' . Convert::raw2sql($tableName) . '" SET "UseSelectedKey" = 1, "SearchKey" = \'\', SearchOnlyKeyID = ? WHERE ID = ?',
                        [
                            $keyId,// assign this search key
                            $record['ID'] // for this record
                        ]
                    );
                    DB::alteration_message("Migrated {$knownClass} SearchKey key #" . $record['ID'], "changed");
                    $changes++;
                }
            }
        }

        if($changes == 0) {
            DB::alteration_message("No typesense key migrations", "changed");
        }
    }
}

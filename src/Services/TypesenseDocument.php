<?php

namespace NSWDPC\Search\Typesense\Services;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;

/**
 * Handles DataObject -> Typesense document translation
 */
abstract class TypesenseDocument
{
    use Configurable;

    /**
     * Default fields that are added to every document
     */
    private static array $default_fields = [
        [
            'name' => 'id',
            'type' => 'int64'
        ],
        [
            'name' => 'ClassName',
            'type' => 'string',
            'facet' => true
        ],
        [
            'name' => 'LastEdited',
            'type' => 'int64'
        ],
        [
            'name' => 'Created',
            'type' => 'int64'
        ],
        /**
         * This field can contain the result data needed to
         * render a result
         */
        [
            'name' => 'TypesenseSearchResultData',
            'type' => 'object[]',
            'index' => false
        ]
    ];

    /**
     * Return the default fields from content
     */
    public static function getDefaultFields(): array
    {
        $defaultFields = static::config()->get('default_fields');
        if (!is_array($defaultFields)) {
            return [];
        } else {
            return $defaultFields;
        }
    }

    /**
     * Get a document from a record for indexing by Typesense
     * If the method getTypesense{fieldName} exists on the record, use that
     * If the method get{fieldName} exists on the record, use that
     * Else use getField(fieldName)
     * Then, do massaging of data values
     */
    public static function get(DataObject $record, array $fields): array
    {
        $document = [];

        // add default fields on
        $defaultFields = static::getDefaultFields();
        $fields = array_merge($defaultFields, $fields);

        foreach ($fields as $field) {

            if (!isset($field['name']) || $field['name'] === '') {
                throw new \RuntimeException("Cannot get a value of a field without a name");
            }

            $fieldName = $field['name'];

            // Logger::log("Finding value for field {$fieldName}", "DEBUG");

            // nominated method names used to get a custom value
            $typesenseMethodName = "getTypesenseValueFor{$fieldName}";
            $genericMethodName = "get{$fieldName}";

            $value = null;
            if ($fieldName == "id") {
                // Logger::log("Getting value for {$fieldName} from ID field", "DEBUG");
                $value = (string)$record->ID;
            } elseif (method_exists($record, $typesenseMethodName)) {
                // Logger::log("Getting value for {$fieldName} from direct method {$typesenseMethodName}", "DEBUG");
                // raw value passed back from getTypesenseValueFor{FieldName}
                $value = $record->{$typesenseMethodName}($field);
            } elseif (method_exists($record, $genericMethodName)) {
                // Logger::log("Getting value for {$fieldName} from direct method {$genericMethodName}", "DEBUG");
                // raw value passed back from get{FieldName}
                $value = $record->{$genericMethodName}();
            } else {
                // Logger::log("Getting value for {$fieldName} from __get()", "DEBUG");
                // Get value from __get()
                $value = $record->__get($fieldName);
            }

            // handle DB fields
            if ($record->hasField($fieldName)) {
                // deal with DB fields
                $dbValue = $record->dbObject($fieldName);
                if ($dbValue instanceof \SilverStripe\ORM\FieldType\DBHTMLText || $dbValue instanceof \SilverStripe\ORM\FieldType\DBHTMLVarchar) {
                    // do not process shortcodes in this field
                    $dbValue->setProcessShortcodes(false);
                    // HTML values are made Plain, so that HTML is not stored in the index
                    $value = $dbValue->Plain();
                } elseif ($field['type'] == 'int64' && ($dbValue instanceof \SilverStripe\ORM\FieldType\DBDate)) {
                    // coerce Date/Datetime values into unix timestamps for DBDate fields that need to be a timestamp
                    $timestamp = $dbValue->getTimestamp();
                    $value = $timestamp > 0 ? $timestamp : null;
                }
            }

            // add field value into data
            $document[$fieldName] = $value;
        }

        if ($document !== []) {
            // each data entry will have an "id" value needs to be a string version of the record ID
            $document['id'] = (string) $record->ID;
        }

        return $document;
    }
}

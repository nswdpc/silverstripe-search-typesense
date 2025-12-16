<?php
namespace NSWDPC\Search\Typesense\Services;

use SilverStripe\ORM\DataObject;

/**
 * Handles DataObject -> Typesense document translation
 */
abstract class TypesenseDocument
{

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
        foreach ($fields as $field) {

            if (!isset($field['name'])) {
                throw new \RuntimeException("Cannot get a value of a field without a name");
            }

            // nominated method names used to get a custom value
            $typesenseMethodName = "getTypesenseValueFor{$field['name']}";
            $genericMethodName = "get{$field['name']}";

            if (method_exists($record, $typesenseMethodName)) {
                // raw value passed back from getTypesenseValueFor{FieldName}
                $value = $record->{$typesenseMethodName}($field);
            } elseif (method_exists($record, $genericMethodName)) {
                // raw value passed back from get{FieldName}
                $value = $record->{$genericMethodName}();
            } else {
                // get value via getField()
                $value = $record->getField($field['name']);
            }

            // handle DB fields
            if ($record->hasField($field['name'])) {
                // deal with DB fields
                $dbValue = $record->dbObject($field['name']);
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
            $document[$field['name']] = $value;
        }

        if ($document !== []) {
            // each data entry will have an "id" value needs to be a string version of the record ID
            $document['id'] = (string) $record->ID;
        }

        return $document;
    }
}
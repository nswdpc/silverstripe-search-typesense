<?php

namespace NSWDPC\Search\Typesense\Extensions;

use SilverStripe\ORM\DataExtension;

class DocumentDataExtension extends DataExtension {

    /**
     * Return appropriate values based on the fields being indexed for Typesense
     * If the method getTypesense{fieldName} exists on the record, use that
     * If the method get{fieldName} exists on the record, use that
     * Else use getField(fieldName)
     * Then, do massaging of data values
     */
    public function getTypesenseDocument(array $fields) {

        // \NSWDPC\Search\Typesense\Services\Logger::log("DocumentDataExtension - getTypesenseDocument", "DEBUG");

        $data = [];
        $owner = $this->getOwner();
        foreach ($fields as $field) {

            if(!isset($field['name'])) {
                throw new \RuntimeException("Cannot get a value of a field without a name");
            }

            // nominated method names used to get a custom value
            $typesenseMethodName = "getTypesenseValueFor{$field['name']}";
            $genericMethodName = "get{$field['name']}";

            if(method_exists($owner, $typesenseMethodName)) {
                // raw value passed back from getTypesense(Field)
                $value = $owner->{$typesenseMethodName}($field);
            } else if(method_exists($owner, $genericMethodName)) {
                // raw value passed back from get(Field)
                $value = $owner->{$genericMethodName}();
            } else {
                // get value via getField()
                $value = $owner->getField($field['name']);
            }

            // handle DB fields
            if($owner->hasField($field['name'])) {
                // deal with DB fields
                $dbValue = $owner->dbObject($field['name']);
                if($dbValue instanceof \SilverStripe\ORM\FieldType\DBHTMLText || $dbValue instanceof \SilverStripe\ORM\FieldType\DBHTMLVarchar) {
                    // HTML values are made Plain, so that HTML is not stored in the index
                    $value = $dbValue->Plain();
                } else if ($field['type'] == 'int64' && ($dbValue instanceof \SilverStripe\ORM\FieldType\DBDate)) {
                    // coerce Date/Datetime values into unix timestamps for DBDate fields that need to be a timestamp
                    $timestamp = $dbValue->getTimestamp();
                    if($timestamp > 0) {
                        $value = $timestamp;
                    } else {
                        $value = null;
                    }
                }
            }

            // add field value into data
            $data[$field['name']] = $value;
        }

        if($data !== []) {
            // each data entry will have an "id" value needs to be a string version of the record ID
            $data['id'] = (string) $owner->ID;
        }

        return $data;
    }

}

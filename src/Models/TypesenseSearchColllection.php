<?php

namespace NSWDPC\Search\Typesense\Models;

/**
 * Represents a collection, with field metadata being pulled from YML configuration
 */
class TypesenseSearchColllection extends DataObject
{

    /**
     * Field to allow administration of the collection within the admin interface
     */
    private static array $db = [
        'Title' => 'Varchar(255)',
        'LinkedClass' => 'Text',
        'IsEnabled' => 'Boolean',
        'Metadata' => 'Text' // applicable metadata from configuration
    ];

    /**
     * Add indexes to fields that are queried, avoid full table scans
     */
    private static array $indexes = [
        'Title' => true,
        'IsEnabled' => true
    ];

    private static string $table_name = 'TypesenseSearchColllection';

    private static string $singular_name = 'Typesense search colllection';

    private static string $plural_names = 'Typesense search colllections';

}
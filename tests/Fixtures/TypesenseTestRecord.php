<?php

declare(strict_types=1);

namespace NSWDPC\Search\Typesense\Tests\Fixtures;

use NSWDPC\Search\Typesense\Extensions\DocumentDataExtension;
use NSWDPC\Search\Typesense\Extensions\RecordChangeHandler;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A plain (unversioned) DataObject used to exercise TypesenseDocument field
 * resolution, DocumentDataExtension and RecordChangeHandler.
 *
 * RecordChangeHandler is safe to attach unconditionally: it is a no-op unless
 * a TypesenseSearchCollection is linked to this class (see SearchHandler::
 * isLinkedToCollections()), so tests that don't care about it are unaffected.
 *
 * @method array getTypesenseDocument(array $fields)
 */
class TypesenseTestRecord extends DataObject implements TestOnly
{
    private static string $table_name = 'TypesenseTestRecord';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'Content' => 'HTMLText',
        'PublishDate' => 'Date',
        'ShowInSearch' => 'Boolean',
        'GenericValue' => 'Varchar(255)',
        'CustomValue' => 'Varchar(255)',
    ];

    private static array $defaults = [
        'ShowInSearch' => true,
    ];

    private static array $extensions = [
        DocumentDataExtension::class,
        RecordChangeHandler::class,
    ];

    /**
     * Exercises the get{Field}() fallback used by TypesenseDocument::get()
     */
    public function getGenericValue(): string
    {
        return 'generic:' . $this->getField('GenericValue');
    }

    /**
     * Exercises the getTypesenseValueFor{Field}() override used by TypesenseDocument::get()
     */
    public function getTypesenseValueForCustomValue(array $field): string
    {
        return 'typesense:' . $this->getField('CustomValue');
    }
}

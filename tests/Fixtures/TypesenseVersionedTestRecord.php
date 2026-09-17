<?php

declare(strict_types=1);

namespace NSWDPC\Search\Typesense\Tests\Fixtures;

use NSWDPC\Search\Typesense\Extensions\RecordChangeHandler;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * A versioned DataObject used to exercise RecordChangeHandler's versioned-record
 * branch (onAfterPublish/onAfterPublishRecursive/onAfterUnpublish, and the
 * onAfterWrite/onBeforeDelete no-op for versioned records).
 *
 * @method void onAfterPublish()
 * @method void onAfterPublishRecursive()
 * @method void onAfterUnpublish()
 */
class TypesenseVersionedTestRecord extends DataObject implements TestOnly
{
    private static string $table_name = 'TypesenseVersionedTestRecord';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'ShowInSearch' => 'Boolean',
    ];

    private static array $defaults = [
        'ShowInSearch' => true,
    ];

    private static array $extensions = [
        Versioned::class,
        RecordChangeHandler::class,
    ];
}

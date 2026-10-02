<?php

declare(strict_types=1);

namespace NSWDPC\Search\Typesense\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Security\InheritedPermissionsExtension;

/**
 * A hierarchical DataObject with granular/logged-in view permission support,
 * used to exercise IncludeInSearchIndex's permission-inheritance walk.
 */
class TypesensePermissionTestRecord extends DataObject implements TestOnly
{
    private static string $table_name = 'TypesensePermissionTestRecord';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'ShowInSearch' => 'Boolean',
    ];

    private static array $defaults = [
        'ShowInSearch' => true,
    ];

    private static array $extensions = [
        Hierarchy::class,
        InheritedPermissionsExtension::class,
    ];
}

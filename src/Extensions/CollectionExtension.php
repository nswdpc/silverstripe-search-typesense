<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\Collection;
use SilverStripe\ORM\DataExtension;

/**
 * Extensions for Collection data model
 *
 * @property Collection|CollectionExtension $owner
 * @extends \SilverStripe\ORM\DataExtension<static>
 */
class CollectionExtension extends DataExtension
{
    /**
     * Add indexes to fields that are queried, avoid full table scans
     */
    private static array $indexes = [
        'Sort' => true,
        'Enabled' => true,
        'Name' => true,
        'RecordClass' => true
    ];

}

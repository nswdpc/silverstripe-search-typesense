<?php

namespace NSWDPC\Search\Typesense\Tests\Fixtures;

use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;

/**
 * This test environment's database does not reliably roll back changes
 * between test methods (nor, in some cases, between test classes run in the
 * same process), so several tests need a proactive way to clear out fixture
 * rows a previous test may have left behind before asserting on counts or on
 * the absence of data.
 *
 * Used by test classes whose assertions depend on a clean slate for the
 * shared TypesenseTestRecord/TypesensePermissionTestRecord fixtures and any
 * TypesenseSearchCollection rows linked to them.
 */
trait ResetsTypesenseFixtureData
{
    protected function resetTypesenseFixtureData(): void
    {
        TypesenseSearchCollection::get()->filter(['RecordClass' => [
            TypesenseTestRecord::class,
            TypesensePermissionTestRecord::class,
        ]])->removeAll();
        TypesenseTestRecord::get()->removeAll();
        TypesensePermissionTestRecord::get()->removeAll();
    }
}

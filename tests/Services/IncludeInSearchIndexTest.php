<?php

namespace NSWDPC\Search\Typesense\Tests\Services;

use NSWDPC\Search\Typesense\Services\IncludeInSearchIndex;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesensePermissionTestRecord;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\InheritedPermissions;

class IncludeInSearchIndexTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
        TypesensePermissionTestRecord::class,
    ];

    public function testCheckReturnsFalseWhenShowInSearchIsFalse(): void
    {
        $record = TypesenseTestRecord::create(['ShowInSearch' => false]);
        $record->write();

        $service = IncludeInSearchIndex::create();
        $this->assertFalse($service->check($record));
    }

    public function testCheckReturnsTrueWhenShowInSearchIsTrueAndNoPermissionsApply(): void
    {
        $record = TypesenseTestRecord::create(['ShowInSearch' => true]);
        $record->write();

        $service = IncludeInSearchIndex::create();
        $this->assertTrue($service->check($record));
    }

    public function testCanShowInSearchReflectsShowInSearchField(): void
    {
        $this->assertFalse(IncludeInSearchIndex::canShowInSearch(
            TypesensePermissionTestRecord::create(['ShowInSearch' => false])
        ));
        $this->assertTrue(IncludeInSearchIndex::canShowInSearch(
            TypesensePermissionTestRecord::create(['ShowInSearch' => true])
        ));
    }

    public function testHasGranularViewPermissionsTrueForOnlyTheseUsers(): void
    {
        $record = TypesensePermissionTestRecord::create([
            'ShowInSearch' => true,
            'CanViewType' => InheritedPermissions::ONLY_THESE_USERS,
        ]);
        $record->write();

        $this->assertTrue(IncludeInSearchIndex::hasGranularViewPermissions($record));
        $this->assertFalse(IncludeInSearchIndex::create()->check($record));
    }

    public function testHasGranularViewPermissionsCanBeDisabledViaConfig(): void
    {
        Config::modify()->set(IncludeInSearchIndex::class, 'check_granular_view_permission', false);

        $record = TypesensePermissionTestRecord::create([
            'ShowInSearch' => true,
            'CanViewType' => InheritedPermissions::ONLY_THESE_USERS,
        ]);
        $record->write();

        $this->assertFalse(IncludeInSearchIndex::hasGranularViewPermissions($record));
        $this->assertTrue(IncludeInSearchIndex::create()->check($record));
    }

    public function testHasLoggedInViewPermissionTrue(): void
    {
        $record = TypesensePermissionTestRecord::create([
            'ShowInSearch' => true,
            'CanViewType' => InheritedPermissions::LOGGED_IN_USERS,
        ]);
        $record->write();

        $this->assertTrue(IncludeInSearchIndex::hasLoggedInViewPermission($record));
        $this->assertFalse(IncludeInSearchIndex::create()->check($record));
    }

    public function testGranularViewPermissionsInheritFromParent(): void
    {
        $parent = TypesensePermissionTestRecord::create([
            'ShowInSearch' => true,
            'CanViewType' => InheritedPermissions::ONLY_THESE_USERS,
        ]);
        $parent->write();

        $child = TypesensePermissionTestRecord::create([
            'ShowInSearch' => true,
            'CanViewType' => InheritedPermissions::INHERIT,
            'ParentID' => $parent->ID,
        ]);
        $child->write();

        $this->assertTrue(IncludeInSearchIndex::hasGranularViewPermissions($child));
        $this->assertFalse(IncludeInSearchIndex::create()->check($child));
    }
}

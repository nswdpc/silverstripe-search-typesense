<?php

namespace NSWDPC\Search\Typesense\Services;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Security\InheritedPermissions;
use SilverStripe\Security\InheritedPermissionsExtension;

/**
 * Determines whether a DataObject should be included in search indexing
 * Projects can use dependency injection to extend this class and determine a result
 */
class IncludeInSearchIndex
{
    use Configurable;
    use Injectable;

    /**
     * Whether to check the granular view permission
     */
    private static bool $check_granular_view_permission = true;

    /**
     * Whether to check the loggedinusers view permission
     */
    private static bool $check_loggedin_view_permission = true;

    /**
     * Check whether a record can be included in search indexing
     */
    public static function check(DataObject $record): bool
    {
        if (!self::canShowInSearch($record)) {
            // overrides all checks
            return false;
        }

        // give an injected variant of this class the ability to determine
        // return null to skip
        $custom = self::customIncludeInSearch($record);
        if (is_bool($custom)) {
            return $custom;
        }

        if (self::hasGranularViewPermissions($record)) {
            // granular permissions - excluded
            return false;
        } elseif (self::hasLoggedInViewPermission($record)) {
            // logged in user permissions - excluded
            return false;
        } else {
            // default allow
            return true;
        }
    }

    /**
     * Determine if the record has a ShowInSearch field and enabled
     */
    public static function canShowInSearch(DataObject $record): bool
    {
        return $record->hasField('ShowInSearch') && $record->ShowInSearch;
    }

    /**
     * This method can be overridden to provide specific logic
     */
    public static function customIncludeInSearch(DataObject $record): ?bool
    {
        return null;
    }

    /**
     * Granular view permissions are determined by the InheritedPermissions::ONLY_THESE_USERS permission
     * assigned to a record or inherited from a parent
     *
     * If a record has granular view permissions it is excluded from search
     *
     * The options for CanViewType are Anyone, LoggedInUsers, OnlyTheseUsers, OnlyTheseMembers, Inherit
     *
     * LoggedInUsers is a separate check in
     *
     */
    public static function hasGranularViewPermissions(DataObject $record): bool
    {
        if (!self::config()->get('check_granular_view_permission')) {
            // not checking this
            return false;
        }

        if ($record->hasExtension(InheritedPermissionsExtension::class)) {
            // check if page has permissions
            if ($record->CanViewType === InheritedPermissions::ONLY_THESE_USERS || $record->CanViewType === InheritedPermissions::ONLY_THESE_MEMBERS) {
                // has a granular view permission set on the record
                return true;
            } elseif ($record->CanViewType === InheritedPermissions::INHERIT
                && ($record->hasExtension(Hierarchy::class) || $record->hasMethod('getParent'))
                && (
                    // @phpstan-ignore method.notFound
                    ($parent = $record->getParent())
                    && ($parent instanceof DataObject)
                    && $parent->hasExtension(InheritedPermissionsExtension::class)
                    && ($parent->CanViewType === InheritedPermissions::ONLY_THESE_USERS || $parent->CanViewType === InheritedPermissions::ONLY_THESE_MEMBERS)
                )) {
                // inherited permission, check parent
                return static::hasGranularViewPermissions($parent);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Granular view permission check for LoggedInUsers
     * This can be turned off if the project requires it
     */
    public static function hasLoggedInViewPermission(DataObject $record): bool
    {

        if (!self::config()->get('check_loggedin_view_permission')) {
            // not checking this
            return false;
        }

        if ($record->hasExtension(InheritedPermissionsExtension::class)) {
            // check if page has permissions
            if ($record->CanViewType === InheritedPermissions::LOGGED_IN_USERS) {
                // has a LoggedInUsers view permission set on the record
                return true;
            } elseif ($record->CanViewType === InheritedPermissions::INHERIT
                && ($record->hasExtension(Hierarchy::class) || $record->hasMethod('getParent'))
                && (
                    // @phpstan-ignore method.notFound
                    ($parent = $record->getParent())
                    && ($parent instanceof DataObject)
                    && $parent->hasExtension(InheritedPermissionsExtension::class)
                    && $parent->CanViewType === InheritedPermissions::LOGGED_IN_USERS
                )) {
                // inherited permission, check parent
                return static::hasLoggedInViewPermission($parent);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }



}

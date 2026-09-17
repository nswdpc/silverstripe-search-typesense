<?php

namespace NSWDPC\Search\Typesense\Tests;

use NSWDPC\Search\Typesense\Extensions\InstantSearchExtension;
use NSWDPC\Search\Typesense\Extensions\ScopedSearchExtension;
use NSWDPC\Search\Typesense\Models\InstantSearch;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Asserts the extension wiring declared in _config/config.yml is in effect
 */
class ConfigTest extends SapphireTest
{
    public function testSiteConfigHasInstantSearchExtension(): void
    {
        $this->assertTrue(SiteConfig::has_extension(InstantSearchExtension::class));
    }

    public function testInstantSearchModelHasScopedSearchExtension(): void
    {
        $this->assertTrue(InstantSearch::has_extension(ScopedSearchExtension::class));
    }
}

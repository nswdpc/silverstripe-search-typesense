<?php

namespace NSWDPC\Search\Typesense\Tests\Admin;

use NSWDPC\Search\Typesense\Admin\TypesenseAdmin;
use NSWDPC\Search\Typesense\Models\InstantSearch;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Models\TypesenseSearchOnlyKey;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class TypesenseAdminTest extends SapphireTest
{
    public function testManagedModelsIncludesTheSearchOnlyKeyModel(): void
    {
        $managedModels = Config::inst()->get(TypesenseAdmin::class, 'managed_models');
        $this->assertSame(
            [
                TypesenseSearchCollection::class,
                InstantSearch::class,
                TypesenseSearchOnlyKey::class,
            ],
            $managedModels
        );
    }
}

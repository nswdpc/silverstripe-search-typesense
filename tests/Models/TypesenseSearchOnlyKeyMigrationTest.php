<?php

namespace NSWDPC\Search\Typesense\Tests\Models;

use NSWDPC\Search\Typesense\Models\InstantSearch;
use NSWDPC\Search\Typesense\Models\TypesenseSearchOnlyKey;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class TypesenseSearchOnlyKeyMigrationTest extends SapphireTest
{
    protected $usesDatabase = true;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();
        Config::modify()->set(TypesenseSearchOnlyKey::class, 'migrate_keys', true);
    }

    /**
     * Write an InstantSearch record with a legacy SearchKey value directly,
     * bypassing validate()/UseSelectedKey handling, to simulate pre-migration data.
     */
    protected function writeLegacyInstantSearch(string $searchKey, string $title = 'Legacy search'): InstantSearch
    {
        $instantSearch = InstantSearch::create([
            'Title' => $title,
            'Nodes' => 'https://search.example.com:443',
            'SearchScope' => '{"filter_by":"a:b"}',
        ]);
        // write without the legacy key so validate() does not reject it, then
        // set the legacy column directly to avoid the model's own key handling
        $instantSearch->write();

        $tableName = DataObject::getSchema()->tableName(InstantSearch::class);
        DB::prepared_query(
            'UPDATE "' . $tableName . '" SET "SearchKey" = ? WHERE "ID" = ?',
            [$searchKey, $instantSearch->ID]
        );
        return InstantSearch::get()->byID($instantSearch->ID);
    }

    public function testMigrateKeysCreatesAnEnabledKeyFromALegacySearchKey(): void
    {
        $instantSearch = $this->writeLegacyInstantSearch('legacy-search-key');

        $changes = TypesenseSearchOnlyKey::migrateKeys();

        $this->assertSame(1, $changes);
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());

        $keys = TypesenseSearchOnlyKey::get();
        $this->assertSame(1, $keys->count());

        $key = $keys->first();
        $this->assertSame('legacy-search-key', $key->KeyVal);
        $this->assertTrue((bool) $key->IsEnabled);
        $this->assertStringContainsString("#{$instantSearch->ID}", $key->Title);
        $this->assertNotNull($key->Created);
        $this->assertNotNull($key->LastEdited);

        $migratedInstantSearch = InstantSearch::get()->byID($instantSearch->ID);
        $this->assertSame('', $migratedInstantSearch->SearchKey);
        $this->assertTrue((bool) $migratedInstantSearch->UseSelectedKey);
        $this->assertSame($key->ID, $migratedInstantSearch->SearchOnlyKeyID);
    }

    public function testMigrateKeysIsIdempotent(): void
    {
        $this->writeLegacyInstantSearch('legacy-search-key');

        $firstRun = TypesenseSearchOnlyKey::migrateKeys();
        $this->assertSame(1, $firstRun);

        $secondRun = TypesenseSearchOnlyKey::migrateKeys();
        $this->assertSame(0, $secondRun);
        $this->assertSame(1, TypesenseSearchOnlyKey::get()->count());
    }

    public function testMigrateKeysSkipsRecordsWithNoSearchKey(): void
    {
        $this->writeLegacyInstantSearch('');

        $changes = TypesenseSearchOnlyKey::migrateKeys();

        $this->assertSame(0, $changes);
        $this->assertSame(0, TypesenseSearchOnlyKey::get()->count());
    }

    public function testMigrateKeysHandlesMultipleRecords(): void
    {
        $this->writeLegacyInstantSearch('key-one', 'Search one');
        $this->writeLegacyInstantSearch('key-two', 'Search two');

        $changes = TypesenseSearchOnlyKey::migrateKeys();

        $this->assertSame(2, $changes);
        $this->assertSame(2, TypesenseSearchOnlyKey::get()->count());

        $values = TypesenseSearchOnlyKey::get()->column('KeyVal');
        sort($values);
        $this->assertSame(['key-one', 'key-two'], $values);
    }

    public function testMigrateKeysMigratesAKeyEvenWhenItWouldFailValidation(): void
    {
        // an invalid/unverifiable key is still migrated as-is; migration does
        // not call ScopedSearch::validateSearchOnlyKey() or contact the server
        $this->writeLegacyInstantSearch('not-a-real-search-only-key');

        $changes = TypesenseSearchOnlyKey::migrateKeys();

        $this->assertSame(1, $changes);
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
        $this->assertSame('not-a-real-search-only-key', TypesenseSearchOnlyKey::get()->first()->KeyVal);
    }

    public function testRequireDefaultRecordsRunsMigrationOnDevBuild(): void
    {
        $this->writeLegacyInstantSearch('legacy-search-key');

        TypesenseSearchOnlyKey::singleton()->requireDefaultRecords();

        $this->assertSame(1, TypesenseSearchOnlyKey::get()->count());
    }

    public function testRequireDefaultRecordsDoesNotThrowWhenMigrationFails(): void
    {
        // no legacy data and no tables misconfigured; this simply asserts
        // requireDefaultRecords() completes without raising
        TypesenseSearchOnlyKey::singleton()->requireDefaultRecords();
        $this->assertSame(0, TypesenseSearchOnlyKey::get()->count());
    }
}

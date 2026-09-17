<?php

namespace NSWDPC\Search\Typesense\Tests\Extensions;

use NSWDPC\Search\Typesense\Models\InstantSearch;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class ScopedSearchExtensionTest extends SapphireTest
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();
    }

    public function testValidateRequiresANonEmptySearchScope(): void
    {
        $instantSearch = InstantSearch::create(['SearchScope' => '']);
        $result = $instantSearch->validate();
        $this->assertFalse($result->isValid());
    }

    public function testValidateRejectsInvalidJsonSearchScope(): void
    {
        $instantSearch = InstantSearch::create(['SearchScope' => 'not json']);
        $result = $instantSearch->validate();
        $this->assertFalse($result->isValid());
    }

    public function testValidatePassesForValidJsonSearchScope(): void
    {
        $instantSearch = InstantSearch::create([
            'Title' => 'A search',
            'Nodes' => 'https://search.example.com:443',
            'SearchScope' => '{"filter_by":"a:b"}',
        ]);
        $result = $instantSearch->validate();
        $this->assertTrue($result->isValid());
    }

    public function testGetTypesenseSearchOnlyKeyPrefersStoredKeyOverEmptyEnvironment(): void
    {
        $instantSearch = InstantSearch::create(['SearchKey' => 'stored-key']);
        $this->assertSame('stored-key', $instantSearch->getTypesenseSearchOnlyKey());
    }

    public function testGetTypesenseScopedSearchKeyReturnsNullWithoutAKey(): void
    {
        $instantSearch = InstantSearch::create(['SearchKey' => '', 'SearchScope' => '{"filter_by":"a:b"}']);
        $this->assertNull($instantSearch->getTypesenseScopedSearchKey());
    }

    public function testGetTypesenseScopedSearchKeyReturnsKeyForValidScope(): void
    {
        $instantSearch = InstantSearch::create([
            'SearchKey' => 'a-search-key',
            'SearchScope' => '{"filter_by":"a:b"}',
        ]);

        $key = $instantSearch->getTypesenseScopedSearchKey();
        $this->assertIsString($key);
        $this->assertNotSame('', $key);
        // generateScopedSearchKey() is a pure local computation, no HTTP call is made
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testGetTypesenseScopedSearchKeyFallsBackToDefaultScopeWhenInvalid(): void
    {
        $instantSearch = InstantSearch::create([
            'SearchKey' => 'a-search-key',
            'SearchScope' => 'not valid json',
        ]);

        $key = $instantSearch->getTypesenseScopedSearchKey();
        $this->assertIsString($key);
        $this->assertNotSame('', $key);
    }
}

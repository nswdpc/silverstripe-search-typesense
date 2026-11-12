<?php

namespace NSWDPC\Search\Typesense\Tests\Extensions;

use NSWDPC\Search\Typesense\Models\InstantSearch;
use NSWDPC\Search\Typesense\Models\TypesenseSearchOnlyKey;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class ScopedSearchExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    #[\Override]
    protected function setUp(): void
    {
        Environment::setEnv('TYPESENSE_SERVER', '');
        Environment::setEnv('TYPESENSE_API_KEY', '');
        Environment::setEnv('TYPESENSE_SEARCH_KEY', 'test-search-key');
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();
    }

    /**
     * Create and write a TypesenseSearchOnlyKey, queueing the server response needed
     * for it to pass validation on write()
     */
    protected function createSearchOnlyKey(string $keyVal, bool $enabled = true): TypesenseSearchOnlyKey
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => substr($keyVal, 0, 4), 'actions' => ['documents:search']],
            ],
        ]);

        $key = TypesenseSearchOnlyKey::create([
            'Title' => 'Test key',
            'KeyVal' => $keyVal,
            'IsEnabled' => $enabled,
        ]);
        $key->write();
        return $key;
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

    public function testGetTypesenseSearchOnlyKeyUsesLinkedKeyWhenUseSelectedKeyIsSet(): void
    {
        $key = $this->createSearchOnlyKey('abcdefgh');
        $instantSearch = InstantSearch::create([
            'UseSelectedKey' => true,
            'SearchOnlyKeyID' => $key->ID,
        ]);
        $this->assertSame('abcdefgh', $instantSearch->getTypesenseSearchOnlyKey());
    }

    public function testGetTypesenseSearchOnlyKeyReturnsEmptyWhenLinkedKeyIsDisabled(): void
    {
        $key = $this->createSearchOnlyKey('abcdefgh', false);
        $instantSearch = InstantSearch::create([
            'UseSelectedKey' => true,
            'SearchOnlyKeyID' => $key->ID,
        ]);
        $this->assertSame('', $instantSearch->getTypesenseSearchOnlyKey());
    }

    public function testGetTypesenseSearchOnlyKeyReturnsEmptyWhenUseSelectedKeyIsSetButNoKeyIsLinked(): void
    {
        $instantSearch = InstantSearch::create([
            'UseSelectedKey' => true,
            'SearchOnlyKeyID' => 0,
        ]);
        $this->assertSame('', $instantSearch->getTypesenseSearchOnlyKey());
    }

    public function testGetTypesenseSearchOnlyKeyFallsBackToEnvironmentWhenUseSelectedKeyIsNotSet(): void
    {
        $key = $this->createSearchOnlyKey('abcdefgh');
        $instantSearch = InstantSearch::create([
            'UseSelectedKey' => false,
            'SearchOnlyKeyID' => $key->ID,
        ]);
        // the linked key is ignored, the environment value is used instead
        $this->assertSame('test-search-key', $instantSearch->getTypesenseSearchOnlyKey());
    }

    public function testGetTypesenseSearchOnlyKeyReturnsEmptyWhenNotUsingSelectedKeyAndEnvironmentIsEmpty(): void
    {
        Environment::setEnv('TYPESENSE_SEARCH_KEY', '');
        $instantSearch = InstantSearch::create(['UseSelectedKey' => false]);
        $this->assertSame('', $instantSearch->getTypesenseSearchOnlyKey());
    }

    public function testGetTypesenseSearchOnlyKeyIgnoresLegacySearchKeyColumn(): void
    {
        Environment::setEnv('TYPESENSE_SEARCH_KEY', '');
        // the deprecated SearchKey column is no longer consulted by either branch
        $instantSearch = InstantSearch::create([
            'SearchKey' => 'legacy-stored-key',
            'UseSelectedKey' => false,
        ]);
        $this->assertSame('', $instantSearch->getTypesenseSearchOnlyKey());
    }

    public function testGetTypesenseScopedSearchKeyReturnsNullWithoutAKey(): void
    {
        Environment::setEnv('TYPESENSE_SEARCH_KEY', '');
        $instantSearch = InstantSearch::create(['UseSelectedKey' => false, 'SearchScope' => '{"filter_by":"a:b"}']);
        $this->assertNull($instantSearch->getTypesenseScopedSearchKey());
    }

    public function testGetTypesenseScopedSearchKeyReturnsNullWhenSelectedKeyIsDisabledEvenIfEnvironmentIsSet(): void
    {
        // TYPESENSE_SEARCH_KEY remains set from setUp(), but is not consulted
        // because UseSelectedKey is set and the linked key is disabled
        $key = $this->createSearchOnlyKey('abcdefgh', false);
        $instantSearch = InstantSearch::create([
            'UseSelectedKey' => true,
            'SearchOnlyKeyID' => $key->ID,
            'SearchScope' => '{"filter_by":"a:b"}',
        ]);
        $this->assertNull($instantSearch->getTypesenseScopedSearchKey());
    }

    public function testGetTypesenseScopedSearchKeyReturnsKeyForValidScope(): void
    {
        $key = $this->createSearchOnlyKey('a-search-key');
        $httpRequestsBeforeScopedKey = TestClientManager::$httpClient->getRequestCount();

        $instantSearch = InstantSearch::create([
            'UseSelectedKey' => true,
            'SearchOnlyKeyID' => $key->ID,
            'SearchScope' => '{"filter_by":"a:b"}',
        ]);

        $scopedKey = $instantSearch->getTypesenseScopedSearchKey();
        $this->assertIsString($scopedKey);
        $this->assertNotSame('', $scopedKey);
        // generateScopedSearchKey() is a pure local computation, no HTTP call is made
        $this->assertSame($httpRequestsBeforeScopedKey, TestClientManager::$httpClient->getRequestCount());
    }

    public function testGetTypesenseScopedSearchKeyFallsBackToDefaultScopeWhenInvalid(): void
    {
        $key = $this->createSearchOnlyKey('a-search-key');

        $instantSearch = InstantSearch::create([
            'UseSelectedKey' => true,
            'SearchOnlyKeyID' => $key->ID,
            'SearchScope' => 'not valid json',
        ]);

        $scopedKey = $instantSearch->getTypesenseScopedSearchKey();
        $this->assertIsString($scopedKey);
        $this->assertNotSame('', $scopedKey);
    }
}

<?php

namespace NSWDPC\Search\Typesense\Tests\Services;

use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Services\ScopedSearch;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class ScopedSearchTest extends SapphireTest
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();
    }

    // -- pure logic, no HTTP --

    public function testGetDefaultScope(): void
    {
        $this->assertSame(['include_fields' => 'Title,TypesenseSearchResultData'], ScopedSearch::getDefaultScope());
    }

    public function testValidateSearchScope(): void
    {
        $this->assertFalse(ScopedSearch::validateSearchScope(''));
        $this->assertFalse(ScopedSearch::validateSearchScope('not json'));
        $this->assertTrue(ScopedSearch::validateSearchScope('{"filter_by":"a:b"}'));
    }

    public function testGetDecodedSearchScope(): void
    {
        $this->assertSame([], ScopedSearch::getDecodedSearchScope(''));
        $this->assertSame(['a' => 'b'], ScopedSearch::getDecodedSearchScope('{"a":"b"}'));
    }

    // -- HTTP-mocked --

    public function testGetScopedApiKeyThrowsForEmptyScope(): void
    {
        $this->expectException(\RuntimeException::class);
        ScopedSearch::getScopedApiKey('a-search-key', []);
    }

    public function testGetScopedApiKeyComputesKeyLocallyWithoutAnHttpCall(): void
    {
        // Typesense's generateScopedSearchKey() is a pure local HMAC computation,
        // it does not call the Typesense server
        $key = ScopedSearch::getScopedApiKey('a-search-key', ['filter_by' => 'a:b']);
        $this->assertNotSame('', $key);
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testValidateSearchOnlyKeyReturnsFalseWhenKeyNotFound(): void
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => 'xyz', 'actions' => ['documents:search']],
            ],
        ]);

        $this->assertFalse(ScopedSearch::validateSearchOnlyKey('abcdefgh'));
    }

    public function testValidateSearchOnlyKeyReturnsFalseWhenActionsAreWrong(): void
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => 'abcd', 'actions' => ['documents:search', 'collections:create']],
            ],
        ]);

        $this->assertFalse(ScopedSearch::validateSearchOnlyKey('abcdefgh'));
    }

    public function testValidateSearchOnlyKeyReturnsTrueForMatchingSearchOnlyKey(): void
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => 'abcd', 'actions' => ['documents:search']],
            ],
        ]);

        $this->assertTrue(ScopedSearch::validateSearchOnlyKey('abcdefgh'));
    }

    public function testValidateSearchOnlyKeyReturnsFalseForEmptyKey(): void
    {
        $this->assertFalse(ScopedSearch::validateSearchOnlyKey(''));
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }
}

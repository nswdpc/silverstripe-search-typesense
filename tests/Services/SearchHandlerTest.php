<?php

namespace NSWDPC\Search\Typesense\Tests\Services;

use NSWDPC\Search\Typesense\Models\SearchResults;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Services\SearchHandler;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class SearchHandlerTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();

        // This environment's test database does not reliably roll back changes
        // between test methods, so proactively clear out anything a previous
        // test (in this class or another) may have left linked to the fixture
        // record class, since several assertions below depend on a clean slate.
        TypesenseSearchCollection::get()->filter(['RecordClass' => TypesenseTestRecord::class])->removeAll();
    }

    protected function createCollection(string $name = 'docs'): TypesenseSearchCollection
    {
        $collection = TypesenseSearchCollection::create([
            'Name' => $name,
            'RecordClass' => TypesenseTestRecord::class,
            'Enabled' => true,
        ]);
        $collection->Metadata = json_encode([
            'name' => $name,
            'fields' => [
                ['name' => 'Title', 'type' => 'string'],
            ],
        ]);
        $collection->write();
        return $collection;
    }

    // -- pure logic, no HTTP --

    public function testEscapeString(): void
    {
        $this->assertSame('plain', SearchHandler::escapeString('plain'));
        $this->assertSame('`has \\`backtick\\``', SearchHandler::escapeString('has `backtick`'));
    }

    public function testEscapeArray(): void
    {
        $this->assertSame(['a', '`b\\`c`'], SearchHandler::escapeArray(['a', 'b`c', 123]));
    }

    public function testSetPerPage(): void
    {
        $handler = SearchHandler::create();
        $this->assertSame(SearchHandler::DEFAULT_PER_PAGE, $handler->setPerPage(0));
        $this->assertSame(SearchHandler::DEFAULT_PER_PAGE, $handler->setPerPage(-5));
        $this->assertSame(SearchHandler::MAX_PER_PAGE, $handler->setPerPage(1000));
        $this->assertSame(25, $handler->setPerPage(25));
    }

    public function testGetStartVarNameRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SearchHandler::create('');
    }

    public function testGetCollectionsForRecordAndIsLinkedToCollections(): void
    {
        $record = TypesenseTestRecord::create();
        $record->write();

        $this->assertNull(SearchHandler::isLinkedToCollections($record));

        $collection = $this->createCollection();

        $linked = SearchHandler::isLinkedToCollections($record);
        $this->assertInstanceOf(\SilverStripe\ORM\DataList::class, $linked);
        $this->assertSame(1, $linked->count());
        $this->assertSame($collection->ID, $linked->first()->ID);
    }

    // -- HTTP-mocked --

    public function testDoSearchReturnsNullForEmptyCollectionName(): void
    {
        $collection = TypesenseSearchCollection::create();
        $handler = SearchHandler::create();
        $this->assertNull($handler->doSearch($collection, 'hello'));
    }

    public function testDoSearchReturnsSearchResultsForStringQuery(): void
    {
        $collection = $this->createCollection();
        TestClientManager::$httpClient->queueJson(200, [
            'found' => 1,
            'hits' => [
                [
                    'document' => ['id' => '1', 'Title' => 'Hello World'],
                    'highlight' => [],
                    'highlights' => [],
                    'text_match' => 100,
                    'text_match_info' => [],
                ],
            ],
        ]);

        $handler = SearchHandler::create();
        $results = $handler->doSearch($collection, 'hello', 0, 10);

        $this->assertInstanceOf(SearchResults::class, $results);
        $this->assertSame(1, $results->count());
        $this->assertSame('Hello World', $results->first()->Title);

        $request = TestClientManager::$httpClient->getLastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('/collections/docs/documents/search', (string) $request->getUri());
        $this->assertStringContainsString('query_by=Title', (string) $request->getUri());
    }

    public function testDoSearchReturnsNullWhenNoHitsKeyInResponse(): void
    {
        $collection = $this->createCollection();
        TestClientManager::$httpClient->queueJson(200, ['found' => 0]);

        $handler = SearchHandler::create();
        $this->assertNull($handler->doSearch($collection, 'hello'));
    }

    public function testDoSearchWithArrayQueryBuildsFilterBy(): void
    {
        $collection = $this->createCollection();
        TestClientManager::$httpClient->queueJson(200, ['found' => 0, 'hits' => []]);

        $handler = SearchHandler::create();
        $results = $handler->doSearch($collection, ['Title' => 'value']);

        $this->assertInstanceOf(SearchResults::class, $results);
        $this->assertSame(0, $results->count());

        $request = TestClientManager::$httpClient->getLastRequest();
        $this->assertStringContainsString('filter_by=Title', urldecode((string) $request->getUri()));
    }

    public function testDoMultiSearchSendsMultiSearchRequest(): void
    {
        TestClientManager::$httpClient->queueJson(200, ['results' => [['found' => 1]]]);

        $handler = SearchHandler::create();
        $result = $handler->doMultiSearch('docs', ['Title' => 'value']);

        $this->assertSame(['results' => [['found' => 1]]], $result);
        $request = TestClientManager::$httpClient->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/multi_search', (string) $request->getUri());
    }

    public function testUpsertToTypesenseReturnsFalseWhenNotLinkedToCollections(): void
    {
        $record = TypesenseTestRecord::create();
        $record->write();

        $this->assertFalse(SearchHandler::upsertToTypesense($record, false));
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testUpsertToTypesenseReturnsFalseWhenExcludedFromIndex(): void
    {
        $this->createCollection();
        $record = TypesenseTestRecord::create(['ShowInSearch' => false]);
        $record->write();

        $this->assertFalse(SearchHandler::upsertToTypesense($record, false));
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testUpsertToTypesenseSuccess(): void
    {
        $this->createCollection();
        $record = TypesenseTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();

        // 1: collection exists check, 2: document upsert
        TestClientManager::$httpClient->queueJson(200, ['name' => 'docs']);
        TestClientManager::$httpClient->queueJson(200, ['id' => (string) $record->ID]);

        $this->assertTrue(SearchHandler::upsertToTypesense($record, false));
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());
    }

    public function testUpsertToTypesenseSkipsCollectionThatDoesNotExistAtServer(): void
    {
        $this->createCollection();
        $record = TypesenseTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();

        // exists() check returns 404 -> collection treated as not existing, no upsert attempted
        TestClientManager::$httpClient->queueJson(404, ['message' => 'Not Found']);

        // success is reported as false because no collections were actually upserted to
        $this->assertFalse(SearchHandler::upsertToTypesense($record, false));
        $this->assertSame(1, TestClientManager::$httpClient->getRequestCount());
    }

    public function testDeleteFromTypesenseSuccess(): void
    {
        $this->createCollection();
        $record = TypesenseTestRecord::create(['Title' => 'A title']);
        $record->write();

        TestClientManager::$httpClient->queueJson(200, ['name' => 'docs']);
        TestClientManager::$httpClient->queueJson(200, ['id' => (string) $record->ID]);

        $this->assertTrue(SearchHandler::deleteFromTypesense($record, false));
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());

        $deleteRequest = TestClientManager::$httpClient->getLastRequest();
        $this->assertSame('DELETE', $deleteRequest->getMethod());
    }
}

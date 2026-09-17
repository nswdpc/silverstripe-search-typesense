<?php

namespace NSWDPC\Search\Typesense\Tests\Models;

use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Fixtures\ResetsTypesenseFixtureData;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesensePermissionTestRecord;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;

class TypesenseSearchCollectionTest extends SapphireTest
{
    use ResetsTypesenseFixtureData;

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
        TypesensePermissionTestRecord::class,
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();
        $this->resetTypesenseFixtureData();
    }

    protected function validMetadata(array $overrides = []): array
    {
        return array_merge([
            'name' => 'a-collection',
            'fields' => [
                ['name' => 'Title', 'type' => 'string'],
            ],
        ], $overrides);
    }

    public function testCollectionExists(): void
    {
        $collection = TypesenseSearchCollection::create(['Name' => 'existing', 'RecordClass' => TypesenseTestRecord::class]);
        $collection->write();

        $this->assertTrue($collection->collectionExists('existing'));
        $this->assertFalse($collection->collectionExists('missing'));
        // excluding the current record should not find itself
        $this->assertFalse($collection->collectionExists('existing', true));
    }

    public function testGetValidRecordClass(): void
    {
        $collection = TypesenseSearchCollection::create();

        $collection->RecordClass = '';
        $this->assertSame('', $collection->getValidRecordClass());

        $collection->RecordClass = 'Not\\A\\Real\\Class';
        $this->assertSame('', $collection->getValidRecordClass());

        $collection->RecordClass = \stdClass::class;
        $this->assertSame('', $collection->getValidRecordClass());

        $collection->RecordClass = TypesenseTestRecord::class;
        $this->assertSame(TypesenseTestRecord::class, $collection->getValidRecordClass());
    }

    public function testValidateRejectsDuplicateNameForNewRecord(): void
    {
        TypesenseSearchCollection::create(['Name' => 'dup-name', 'RecordClass' => TypesenseTestRecord::class])->write();

        $this->expectException(ValidationException::class);
        TypesenseSearchCollection::create(['Name' => 'dup-name', 'RecordClass' => TypesenseTestRecord::class])->write();
    }

    public function testValidateOfExistingRecordChecksMetadataAndRecordClass(): void
    {
        $collection = TypesenseSearchCollection::create(['Name' => 'initial-name', 'RecordClass' => TypesenseTestRecord::class]);
        $collection->write();

        // mutate in-memory (do not re-write) to exercise the isInDB() validate() branch
        $collection->Metadata = json_encode(['unexpected_key' => true]);

        $result = $collection->validate();
        $this->assertFalse($result->isValid());
    }

    public function testGetCollectionFieldsAndQueryByFields(): void
    {
        $collection = TypesenseSearchCollection::create();
        $collection->Metadata = json_encode($this->validMetadata([
            'fields' => [
                ['name' => 'Title', 'type' => 'string'],
                ['name' => 'Views', 'type' => 'int32'],
                ['name' => 'Hidden', 'type' => 'string', 'index' => false],
            ],
        ]));

        $fields = $collection->getCollectionFields();
        $this->assertCount(3, $fields);

        $queryByFields = $collection->getCollectionFieldsForQueryBy();
        $this->assertSame(['Title'], $queryByFields);
    }

    public function testGetCollectionNameFromMetadata(): void
    {
        $collection = TypesenseSearchCollection::create();
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'from-metadata']));
        $this->assertSame('from-metadata', $collection->getCollectionName());
    }

    public function testGetMetadataAsArrayReturnsEmptyArrayForInvalidJson(): void
    {
        $collection = TypesenseSearchCollection::create();
        $collection->Metadata = 'not json';
        $this->assertSame([], $collection->getMetadataAsArray());
    }

    public function testValidateMetadataThrowsForMissingRequiredFields(): void
    {
        $collection = TypesenseSearchCollection::create();
        $collection->Metadata = json_encode(['name' => 'a-collection']);

        $this->expectException(ValidationException::class);
        $collection->validateMetadata();
    }

    public function testValidateMetadataThrowsForUnexpectedKey(): void
    {
        $collection = TypesenseSearchCollection::create();
        $collection->Metadata = json_encode($this->validMetadata(['not_a_real_key' => 'value']));

        $this->expectException(\OutOfRangeException::class);
        $collection->validateMetadata();
    }

    public function testValidateMetadataReturnsMetadataWhenValid(): void
    {
        $collection = TypesenseSearchCollection::create();
        $metadata = $this->validMetadata();
        $collection->Metadata = json_encode($metadata);
        $this->assertSame($metadata, $collection->validateMetadata());
    }

    public function testOnBeforeWriteSetsNameFromMetadataAndPrettifiesIt(): void
    {
        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'set-via-metadata']));
        $collection->write();

        $this->assertSame('set-via-metadata', $collection->Name);
        // pretty-printed JSON is indented
        $this->assertStringContainsString("\n", (string) $collection->Metadata);
    }

    public function testFindOrCreateCreatesNewRecord(): void
    {
        $collection = TypesenseSearchCollection::findOrCreate(
            'created-collection',
            TypesenseTestRecord::class,
            $this->validMetadata(['name' => 'created-collection']),
            true
        );

        $this->assertTrue($collection->isInDB());
        $this->assertTrue((bool) $collection->Enabled);
        $this->assertTrue((bool) $collection->IsCreatedFromConfig);
        $this->assertSame(TypesenseTestRecord::class, $collection->RecordClass);
    }

    public function testFindOrCreateReturnsExistingRecordByName(): void
    {
        $original = TypesenseSearchCollection::create([
            'Name' => 'already-exists',
            'RecordClass' => TypesenseTestRecord::class,
        ]);
        $original->write();

        $found = TypesenseSearchCollection::findOrCreate(
            'already-exists',
            TypesenseTestRecord::class,
            $this->validMetadata(['name' => 'already-exists']),
            false
        );

        $this->assertSame($original->ID, $found->ID);
    }

    public function testGetConfiguredCollections(): void
    {
        Config::modify()->set(TypesenseSearchCollection::class, 'collections', [
            TypesenseTestRecord::class => $this->validMetadata(),
        ]);

        $collections = TypesenseSearchCollection::getConfiguredCollections();
        $this->assertArrayHasKey(TypesenseTestRecord::class, $collections);
    }

    public function testRequireDefaultRecordsCreatesConfiguredCollections(): void
    {
        Config::modify()->set(TypesenseSearchCollection::class, 'collections', [
            TypesenseTestRecord::class => $this->validMetadata(['name' => 'from-config']),
        ]);

        $collection = TypesenseSearchCollection::create();
        $collection->requireDefaultRecords();

        $found = TypesenseSearchCollection::get()->filter(['Name' => 'from-config'])->first();
        $this->assertNotNull($found);
        $this->assertTrue((bool) $found->IsCreatedFromConfig);
    }

    public function testProvidePermissionsListsExpectedCodes(): void
    {
        $codes = array_keys(TypesenseSearchCollection::create()->providePermissions());
        $this->assertSame(
            [
                'TYPESENSE_COLLECTION_VIEW',
                'TYPESENSE_COLLECTION_EDIT',
                'TYPESENSE_COLLECTION_CREATE',
                'TYPESENSE_COLLECTION_DELETE',
                'TYPESENSE_COLLECTION_REINDEX',
            ],
            $codes
        );
    }

    public function testCanEditRequiresPermission(): void
    {
        // ensure no member from an earlier test is still logged in
        $this->logOut();

        $collection = TypesenseSearchCollection::create();
        $this->assertFalse($collection->canEdit());

        $this->logInWithPermission('TYPESENSE_COLLECTION_EDIT');
        $this->assertTrue($collection->canEdit());
    }

    // -- HTTP-mocked --

    public function testCreateAtServerCreatesCollectionWhenMetadataValid(): void
    {
        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'create-at-server']));
        $collection->write();

        TestClientManager::$httpClient->queueJson(201, ['name' => 'create-at-server', 'created_at' => time()]);

        $this->assertTrue($collection->createAtServer());

        $request = TestClientManager::$httpClient->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/collections', (string) $request->getUri());
    }

    public function testCreateAtServerThrowsForInvalidMetadata(): void
    {
        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode(['name' => 'missing-fields-key']);
        $collection->write();

        $this->expectException(ValidationException::class);
        $collection->createAtServer();
    }

    public function testDeleteFromServerReturnsFalseForEmptyCollectionName(): void
    {
        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        // deleteFromServer() catches its own "empty name" exception and returns false
        $this->assertFalse($collection->deleteFromServer());
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testDeleteFromServerSuccess(): void
    {
        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'delete-from-server']));
        $collection->write();

        TestClientManager::$httpClient->queueJson(200, ['name' => 'delete-from-server']);

        $this->assertTrue($collection->deleteFromServer());
        $request = TestClientManager::$httpClient->getLastRequest();
        $this->assertSame('DELETE', $request->getMethod());
    }

    public function testDeleteFromServerReturnsFalseOnServerError(): void
    {
        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'delete-error']));
        $collection->write();

        TestClientManager::$httpClient->queueJson(404, ['message' => 'Not Found']);

        $this->assertFalse($collection->deleteFromServer());
    }

    public function testBatchedImportWhenCollectionAlreadyExists(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A record', 'ShowInSearch' => true]);
        $record->write();

        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'batch-existing']));
        $collection->write();

        // 1: exists() check (200 = exists), 2: documents import (raw JSONL)
        TestClientManager::$httpClient->queueJson(200, ['name' => 'batch-existing']);
        TestClientManager::$httpClient->queueRaw(200, json_encode(['success' => true, 'id' => (string) $record->ID]));

        $count = $collection->batchedImport(['ID' => 'ASC'], 100, 0);

        $this->assertSame(1, $count);
        $this->assertCount(1, $collection->getImportSuccesses());
        $this->assertCount(0, $collection->getImportErrors());
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());

        $importRequest = TestClientManager::$httpClient->getRequests()[1];
        $this->assertSame('POST', $importRequest->getMethod());
        $this->assertStringContainsString('/documents/import', (string) $importRequest->getUri());
    }

    public function testBatchedImportCreatesCollectionWhenNotYetAtServer(): void
    {
        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'batch-new']));
        $collection->write();

        // 1: exists() check (404 = does not exist), 2: create collection, 3: documents import (no records)
        TestClientManager::$httpClient->queueJson(404, ['message' => 'Not Found']);
        TestClientManager::$httpClient->queueJson(201, ['name' => 'batch-new', 'created_at' => time()]);

        $count = $collection->batchedImport(['ID' => 'ASC'], 100, 0);

        // no records exist for this collection, so the import call is never made
        $this->assertSame(0, $count);
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());
        $this->assertSame('POST', TestClientManager::$httpClient->getLastRequest()->getMethod());
    }

    public function testBatchedImportRecordsFailuresFromResult(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A record', 'ShowInSearch' => true]);
        $record->write();

        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'batch-failure']));
        $collection->write();

        TestClientManager::$httpClient->queueJson(200, ['name' => 'batch-failure']);
        TestClientManager::$httpClient->queueRaw(200, json_encode([
            'success' => false,
            'id' => (string) $record->ID,
            'error' => 'Field `Title` is invalid',
        ]));

        $count = $collection->batchedImport(['ID' => 'ASC'], 100, 0);

        $this->assertSame(1, $count);
        $this->assertCount(0, $collection->getImportSuccesses());
        $this->assertCount(1, $collection->getImportErrors());
    }

    public function testImportLoopsUntilNoMoreRecordsFound(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A record', 'ShowInSearch' => true]);
        $record->write();

        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class]);
        $collection->Metadata = json_encode($this->validMetadata(['name' => 'batch-loop']));
        $collection->write();

        // exists() is only queried once per Typesense\Client (Collection caches the result),
        // so a single response covers every batch's createAtTypesense() call in the loop
        TestClientManager::$httpClient->queueJson(200, ['name' => 'batch-loop']);
        TestClientManager::$httpClient->queueRaw(200, json_encode(['success' => true, 'id' => (string) $record->ID]));

        $total = $collection->import(1, ['ID' => 'ASC'], false);

        $this->assertSame(1, $total);
        // one exists() check + one import call for the single populated batch
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());
    }
}

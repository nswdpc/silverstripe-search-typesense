<?php

namespace NSWDPC\Search\Typesense\Tests\Jobs;

use NSWDPC\Search\Typesense\Jobs\SyncJob;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use NSWDPC\Search\Typesense\Tests\Mocks\FakeQueuedJobService;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class SyncJobTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();

        TypesenseSearchCollection::get()->filter(['RecordClass' => TypesenseTestRecord::class])->removeAll();
        TypesenseTestRecord::get()->removeAll();
    }

    public function testProcessFailsWithoutACollectionName(): void
    {
        $job = new SyncJob('');
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testProcessFailsWhenCollectionCannotBeFound(): void
    {
        $job = new SyncJob('does-not-exist');
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testProcessImportsAnExistingDbCollectionNotYetAtServer(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();

        $collection = TypesenseSearchCollection::create(['RecordClass' => TypesenseTestRecord::class, 'Enabled' => true]);
        $collection->Metadata = json_encode([
            'name' => 'sync-docs',
            'fields' => [['name' => 'Title', 'type' => 'string']],
        ]);
        $collection->write();

        // 1: createAtServer() creates the collection, 2: batchedImport()'s exists() check, 3: documents import
        TestClientManager::$httpClient->queueJson(201, ['name' => 'sync-docs', 'created_at' => time()]);
        TestClientManager::$httpClient->queueJson(200, ['name' => 'sync-docs']);
        TestClientManager::$httpClient->queueRaw(200, json_encode(['success' => true, 'id' => (string) $record->ID]));

        $job = new SyncJob('sync-docs', 0, 100, 0);
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(3, TestClientManager::$httpClient->getRequestCount());
    }

    public function testAfterCompleteRequeuesForNextBatchWhenMoreRecordsRemain(): void
    {
        $fakeQueue = new FakeQueuedJobService();
        Injector::inst()->registerService($fakeQueue, QueuedJobService::class);

        $job = new SyncJob('sync-docs', 0, 100, 0);
        // lastBatchCount is stored via AbstractQueuedJob's magic __set()/jobData
        $job->lastBatchCount = 100;

        $job->afterComplete();

        $queuedJob = $fakeQueue->getLastJob();
        $this->assertInstanceOf(SyncJob::class, $queuedJob);
    }

    public function testAfterCompleteDoesNotRequeueWhenCompleteAndNotRepeating(): void
    {
        $fakeQueue = new FakeQueuedJobService();
        Injector::inst()->registerService($fakeQueue, QueuedJobService::class);

        $job = new SyncJob('sync-docs', 0, 100, 0);
        // lastBatchCount defaults to unset/null - treated as "no more records" and not > 0

        $job->afterComplete();

        $this->assertSame([], $fakeQueue->queued);
    }

    public function testAfterCompleteRequeuesRepeatingJobWhenComplete(): void
    {
        $fakeQueue = new FakeQueuedJobService();
        Injector::inst()->registerService($fakeQueue, QueuedJobService::class);

        $job = new SyncJob('sync-docs', 24, 100, 0);
        $job->lastBatchCount = 0;

        $job->afterComplete();

        $queuedJob = $fakeQueue->getLastJob();
        $this->assertInstanceOf(SyncJob::class, $queuedJob);
        $this->assertNotNull($fakeQueue->queued[0]['startAfter']);
    }
}

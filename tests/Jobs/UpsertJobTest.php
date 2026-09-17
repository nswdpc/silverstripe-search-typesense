<?php

namespace NSWDPC\Search\Typesense\Tests\Jobs;

use NSWDPC\Search\Typesense\Jobs\UpsertJob;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use NSWDPC\Search\Typesense\Tests\Mocks\FakeQueuedJobService;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class UpsertJobTest extends SapphireTest
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

    public function testGetTitle(): void
    {
        $job = new UpsertJob(42, TypesenseTestRecord::class);
        $this->assertStringContainsString('42', $job->getTitle());
        $this->assertStringContainsString(TypesenseTestRecord::class, $job->getTitle());
    }

    public function testQueueMyselfQueuesAnUpsertJobForTheRecord(): void
    {
        $fakeQueue = new FakeQueuedJobService();
        Injector::inst()->registerService($fakeQueue, QueuedJobService::class);

        $record = TypesenseTestRecord::create(['Title' => 'A title']);
        $record->write();

        UpsertJob::queueMyself($record);

        $job = $fakeQueue->getLastJob();
        $this->assertInstanceOf(UpsertJob::class, $job);
    }

    public function testProcessUpsertsLinkedRecordToTypesense(): void
    {
        TypesenseSearchCollection::create(['Name' => 'docs', 'RecordClass' => TypesenseTestRecord::class, 'Enabled' => true])->write();

        $record = TypesenseTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();

        TestClientManager::$httpClient->queueJson(200, ['name' => 'docs']);
        TestClientManager::$httpClient->queueJson(200, ['id' => (string) $record->ID]);

        $job = new UpsertJob($record->ID, $record::class);
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());
    }

    public function testProcessCompletesWhenRecordNoLongerExists(): void
    {
        $job = new UpsertJob(999999, TypesenseTestRecord::class);
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }
}

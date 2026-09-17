<?php

namespace NSWDPC\Search\Typesense\Tests\Jobs;

use NSWDPC\Search\Typesense\Jobs\DeleteJob;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use NSWDPC\Search\Typesense\Tests\Mocks\FakeQueuedJobService;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class DeleteJobTest extends SapphireTest
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

        TypesenseSearchCollection::get()->filter(['RecordClass' => TypesenseTestRecord::class])->removeAll();
        TypesenseTestRecord::get()->removeAll();
    }

    public function testGetTitle(): void
    {
        $job = new DeleteJob(42, TypesenseTestRecord::class);
        $this->assertStringContainsString('42', $job->getTitle());
        $this->assertStringContainsString(TypesenseTestRecord::class, $job->getTitle());
    }

    public function testQueueMyselfQueuesADeleteJobForTheRecord(): void
    {
        $fakeQueue = new FakeQueuedJobService();
        Injector::inst()->registerService($fakeQueue, QueuedJobService::class);

        $record = TypesenseTestRecord::create(['Title' => 'A title']);
        $record->write();

        DeleteJob::queueMyself($record);

        $this->assertInstanceOf(DeleteJob::class, $fakeQueue->getLastJob());
    }

    public function testProcessDeletesLinkedRecordFromTypesense(): void
    {
        TypesenseSearchCollection::create(['Name' => 'docs', 'RecordClass' => TypesenseTestRecord::class, 'Enabled' => true])->write();

        // process() looks up the record by ID via getObject('Record') and requires
        // it to still exist locally, so (unlike a real delete workflow) the local
        // row is left in place here to exercise that lookup
        $record = TypesenseTestRecord::create(['Title' => 'A title']);
        $record->write();

        TestClientManager::$httpClient->queueJson(200, ['name' => 'docs']);
        TestClientManager::$httpClient->queueJson(200, ['id' => (string) $record->ID]);

        $job = new DeleteJob($record->ID, TypesenseTestRecord::class);
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());
        $deleteRequest = TestClientManager::$httpClient->getRequests()[1];
        $this->assertSame('DELETE', $deleteRequest->getMethod());
    }

    public function testProcessCompletesWhenRecordNoLongerExists(): void
    {
        $job = new DeleteJob(999999, TypesenseTestRecord::class);
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }
}

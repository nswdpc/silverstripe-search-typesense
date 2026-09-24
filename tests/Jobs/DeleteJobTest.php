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

        DeleteJob::queueMyself(123, TypesenseTestRecord::class, ['docs']);

        $job = $fakeQueue->getLastJob();
        $this->assertInstanceOf(DeleteJob::class, $job);
        $this->assertSame(123, $job->RecordID);
        $this->assertSame(TypesenseTestRecord::class, $job->RecordType);
        $this->assertSame(['docs'], $job->CollectionNames);
    }

    public function testProcessDeletesRecordFromGivenCollectionsWithoutNeedingTheLocalRowToExist(): void
    {
        // the local record is deliberately never written: process() is queued from
        // onBeforeDelete()/onAfterUnpublish() and typically runs after the row is
        // gone, so it must not depend on the record still existing locally
        TestClientManager::$httpClient->queueJson(200, ['name' => 'docs']);
        TestClientManager::$httpClient->queueJson(200, ['id' => '123']);

        $job = new DeleteJob(123, TypesenseTestRecord::class, ['docs']);
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(2, TestClientManager::$httpClient->getRequestCount());
        $deleteRequest = TestClientManager::$httpClient->getRequests()[1];
        $this->assertSame('DELETE', $deleteRequest->getMethod());
        $this->assertStringContainsString('/collections/docs/documents/123', (string) $deleteRequest->getUri());
    }

    public function testProcessCompletesWithoutCollectionNames(): void
    {
        $job = new DeleteJob(999999, TypesenseTestRecord::class, []);
        $job->process();

        $this->assertTrue($job->jobFinished());
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }
}

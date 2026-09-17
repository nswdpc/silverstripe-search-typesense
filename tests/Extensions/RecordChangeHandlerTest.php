<?php

namespace NSWDPC\Search\Typesense\Tests\Extensions;

use NSWDPC\Search\Typesense\Jobs\DeleteJob;
use NSWDPC\Search\Typesense\Jobs\UpsertJob;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseVersionedTestRecord;
use NSWDPC\Search\Typesense\Tests\Mocks\FakeQueuedJobService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class RecordChangeHandlerTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
        TypesenseVersionedTestRecord::class,
    ];

    protected FakeQueuedJobService $fakeQueue;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeQueue = new FakeQueuedJobService();
        Injector::inst()->registerService($this->fakeQueue, QueuedJobService::class);

        // This test environment's database does not reliably roll back changes
        // between test methods, so proactively clear out anything a previous
        // test may have left linked to the fixture record classes.
        TypesenseSearchCollection::get()->filter(['RecordClass' => [
            TypesenseTestRecord::class,
            TypesenseVersionedTestRecord::class,
        ]])->removeAll();
        TypesenseTestRecord::get()->removeAll();
        TypesenseVersionedTestRecord::get()->removeAll();

        TypesenseSearchCollection::create(['Name' => 'unversioned-docs', 'RecordClass' => TypesenseTestRecord::class, 'Enabled' => true])->write();
        TypesenseSearchCollection::create(['Name' => 'versioned-docs', 'RecordClass' => TypesenseVersionedTestRecord::class, 'Enabled' => true])->write();
    }

    public function testOnAfterWriteQueuesUpsertJobForUnversionedRecord(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();

        $this->assertInstanceOf(UpsertJob::class, $this->fakeQueue->getLastJob());
    }

    public function testOnBeforeDeleteQueuesDeleteJobForUnversionedRecord(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();
        $this->fakeQueue->queued = [];

        $record->delete();

        $this->assertInstanceOf(DeleteJob::class, $this->fakeQueue->getLastJob());
    }

    public function testOnAfterWriteIsANoOpForVersionedRecord(): void
    {
        $record = TypesenseVersionedTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();

        $this->assertSame([], $this->fakeQueue->queued);
    }

    public function testOnBeforeDeleteIsANoOpForVersionedRecord(): void
    {
        $record = TypesenseVersionedTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();
        $this->fakeQueue->queued = [];

        $record->delete();

        $this->assertSame([], $this->fakeQueue->queued);
    }

    /**
     * onAfterPublish/onAfterPublishRecursive/onAfterUnpublish are invoked directly here
     * (rather than via a real publish/unpublish action) to isolate RecordChangeHandler's
     * own dispatch logic from the versioned module's exact publish call sequence.
     */
    public function testOnAfterPublishQueuesUpsertJobForVersionedRecord(): void
    {
        $record = TypesenseVersionedTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();
        $this->fakeQueue->queued = [];

        $record->onAfterPublish();

        $this->assertInstanceOf(UpsertJob::class, $this->fakeQueue->getLastJob());
    }

    public function testOnAfterPublishRecursiveQueuesUpsertJobForVersionedRecord(): void
    {
        $record = TypesenseVersionedTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();
        $this->fakeQueue->queued = [];

        $record->onAfterPublishRecursive();

        $this->assertInstanceOf(UpsertJob::class, $this->fakeQueue->getLastJob());
    }

    public function testOnAfterUnpublishQueuesDeleteJobForVersionedRecord(): void
    {
        $record = TypesenseVersionedTestRecord::create(['Title' => 'A title', 'ShowInSearch' => true]);
        $record->write();
        $this->fakeQueue->queued = [];

        $record->onAfterUnpublish();

        $this->assertInstanceOf(DeleteJob::class, $this->fakeQueue->getLastJob());
    }
}

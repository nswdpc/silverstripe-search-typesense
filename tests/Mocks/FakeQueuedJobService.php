<?php

namespace NSWDPC\Search\Typesense\Tests\Mocks;

use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * A stand-in for Symbiote\QueuedJobs\Services\QueuedJobService that records
 * queued jobs instead of persisting/running them.
 *
 * Register via:
 *   Injector::inst()->registerService(new FakeQueuedJobService(), QueuedJobService::class);
 *
 * Both call sites used by this module (QueuedJobService::singleton() and
 * Injector::inst()->get(QueuedJobService::class)) resolve through the
 * Injector, and neither call site type-hints the return value, so a
 * duck-typed replacement is sufficient - it does not need to extend
 * QueuedJobService itself.
 */
class FakeQueuedJobService
{
    /**
     * @var array<int, array{job: QueuedJob, startAfter: ?string}>
     */
    public array $queued = [];

    public function queueJob(QueuedJob $job, $startAfter = null, $userId = null, $queueName = null): int
    {
        $this->queued[] = [
            'job' => $job,
            'startAfter' => $startAfter,
        ];
        return count($this->queued);
    }

    public function getLastJob(): ?QueuedJob
    {
        if ($this->queued === []) {
            return null;
        }

        return $this->queued[count($this->queued) - 1]['job'];
    }
}

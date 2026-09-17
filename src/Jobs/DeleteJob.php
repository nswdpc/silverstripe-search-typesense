<?php

namespace NSWDPC\Search\Typesense\Jobs;

use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\SearchHandler;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Queued job for deleting a record from its Typesense collections
 *
 * The collections to delete from are resolved and captured at queue time
 * (while the record still exists) rather than at process time, since by the
 * time this job actually runs the local record will usually have already
 * been deleted (it is queued from onBeforeDelete/onAfterUnpublish).
 * @property string[] $CollectionNames
 */
class DeleteJob extends AbstractQueuedJob
{
    /**
     * @param string[] $collectionNames
     */
    public function __construct(int $recordId = 0, string $recordClassName = '', array $collectionNames = [])
    {
        if ($recordId > 0 && $recordClassName !== '' && class_exists($recordClassName)) {
            // emulate setObject so getObject works
            $this->RecordID = $recordId;
            $this->RecordType = $recordClassName;
            $this->CollectionNames = $collectionNames;
        }
    }

    public function getTitle()
    {
        return _t(
            self::class . ".JOB_TITLE",
            "Delete a record from Typesense collections - #{id} - {type}",
            [
                'id' => $this->RecordID,
                'type' => $this->RecordType
            ]
        );
    }

    /**
     * Queue job immediately
     * @param string[] $collectionNames the Typesense collection names to delete the record from
     */
    public static function queueMyself(int $recordId, string $recordClassName, array $collectionNames)
    {
        $job = new self($recordId, $recordClassName, $collectionNames);
        Logger::log("Queued Typesense DeleteJob for record #{$recordId}", "DEBUG");
        return QueuedJobService::singleton()->queueJob($job);
    }

    /**
     * Process
     */
    public function process()
    {
        try {
            $collectionNames = $this->CollectionNames ?? [];
            if (SearchHandler::deleteDocumentFromCollections((int) $this->RecordID, $collectionNames)) {
                $this->addMessage('Deleted OK');
            } else {
                $this->addMessage('Delete failure or partial success - record might not be linked to any collections, check logs');
            }
        } catch (\Exception $exception) {
            Logger::log("Failed: " . $exception->getMessage(), "NOTICE");
        }

        // job is complete regardless of outcome, no point in hammering the Typesense server
        $this->isComplete = true;
    }
}

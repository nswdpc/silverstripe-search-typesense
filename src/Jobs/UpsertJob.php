<?php

namespace NSWDPC\Search\Typesense\Jobs;

use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\SearchHandler;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Queued job for upserting a record to its Typesense collections
 */
class UpsertJob extends AbstractQueuedJob
{
    public function __construct(int $recordId = 0, string $recordClassName = '')
    {
        if ($recordId > 0 && $recordClassName !== '' && class_exists($recordClassName)) {
            // emulate setObject so getObject works
            $this->RecordID = $recordId;
            $this->RecordType = $recordClassName;
        }
    }

    public function getTitle()
    {
        return _t(
            self::class . ".JOB_TITLE",
            "Upsert a record to Typesense collections - #{id} - {type}",
            [
                'id' => $this->RecordID,
                'type' => $this->RecordType
            ]
        );
    }

    /**
     * Queue job immediately
     */
    public static function queueMyself(DataObject $record)
    {
        $job = new self($record->ID, $record::class);
        Logger::log("Queued Typesense UpsertJob for record #{$record->ID}", "DEBUG");
        return QueuedJobService::singleton()->queueJob($job);
    }

    /**
     * Process
     */
    public function process()
    {
        try {
            $record = $this->getObject('Record');
            if (!$record || !$record->exists()) {
                throw new \RuntimeException("The record {$this->RecordID}/{$this->RecordType} does not exist");
            }

            if (SearchHandler::upsertToTypesense($record, false)) {
                $this->addMessage('Upserted OK');
            } else {
                $this->addMessage('Upsert failure or partial success - record might not be linked to any collections, check logs');
            }
        } catch (\Exception $exception) {
            Logger::log("Failed: " . $exception->getMessage(), "NOTICE");
        }

        // job is complete regardless of outcome, no point in hammering the Typesense server
        $this->isComplete = true;
    }
}

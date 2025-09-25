<?php

namespace NSWDPC\Search\Typesense\Jobs;

use ElliotSawyer\SilverstripeTypesense\Collection;
use ElliotSawyer\SilverstripeTypesense\Typesense;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Services\Logger;
use SilverStripe\ORM\DataList;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Queued job for syncing data to the Typesense server
 */
class SyncJob extends AbstractQueuedJob
{

    public function __construct(string $collectionName = '', int $repeatHours = 0, int $batchLimit = 100, int $batchStart = 0)
    {
        $this->collectionName = trim($collectionName);
        $this->repeatHours = abs($repeatHours);
        $this->batchLimit = $batchLimit;
        $this->batchStart = $batchStart;
    }

    public function getTitle()
    {
        return _t(
            self::class . ".JOB_TITLE",
            "Sync collection to Typesense. Collection={collection} Batch={limit},{start} Repeat={repeat}h",
            [
                'collection' => $this->collectionName,
                'limit' => $this->batchLimit,
                'start' => $this->batchStart,
                'repeat' => $this->repeatHours
            ]
        );
    }

    /**
     * Process
     */
    public function process()
    {
        try {
            if(!$this->collectionName) {
                throw new \Exception("Please provide a collection name");
            }

            // Find or make the collection from configuration
            $collection = null;
            $collections = Typesense::config()->get('collections') ?? [];
            foreach ($collections as $recordClass => $collectionData) {
                $collectionName = $collectionData['name'] ?? null;
                if($collectionName == $this->collectionName) {
                    $collection = Collection::find_or_make($collectionName, $recordClass, $collectionData);
                    break;
                }
            }

            if(!$collection) {
                // no configured collection in YML.. try to get one from
                $this->addMessage("No configured collection found for '{$this->collectionName}', trying to find the collection in the DB only");
                $collection = Collection::get()
                    ->filter([
                        'Enabled' => true,
                        'Name' => $this->collectionName
                    ])->first();
            }

            if(!$collection || !$collection->isInDB()) {
                throw new \Exception("The collection '{$this->collectionName}' could not be found or created, maybe it is not enabled?");
            }

            $manager = new ClientManager();
            $client = $manager->getConfiguredClient();

            // check if collection exists
            $exists = $client->collections[$collection->Name]->exists();
            if(!$exists) {
                // if it doesn't, create it
                $this->addMessage("Collection '{$collection->Name}' does not exist");
                $response = $collection->syncWithTypesenseServer();
                $this->addMessage($response);
            }

            $this->addMessage("Collection '{$collection->Name}' batch import {$this->batchLimit} from {$this->batchStart}");
            $this->lastBatchCount = $collection->batchedImport(
                ['ID' => 'DESC'],
                $this->batchLimit,
                $this->batchStart
            );

            if($this->lastBatchCount == 0) {
                // if there are no more records..
                $this->addMessage("Collection '{$collection->Name}' batch import complete");
            } else {
                // start at the next page
                $this->addMessage("Collection '{$collection->Name}' batch import next");
            }
            $this->isComplete = true;
        } catch (\UnexpectedValueException $unexpectedValueException) {
            $this->addMessage("Failed to run: " . $unexpectedValueException->getMessage());
            // on error mark as complete
            $this->isComplete = true;
        } catch (\Exception $exception) {
            $this->addMessage("ERROR: " . $exception->getMessage());
            // on error mark as complete
            $this->isComplete = true;
        }
    }

    /**
     * Requeue job if configured
     */
    public function afterComplete() {
        $job = null;
        $startAt = null;
        if($this->lastBatchCount === 0 && $this->repeatHours > 0) {
            // complete and repeating
            $job = new SyncJob(
                $this->collectionName,
                $this->repeatHours,
                $this->batchLimit,// re-use batch limit
                0,// start again
            );
            $rdt = DBDatetime::now();
            $rdt->modify("+{$this->repeatHours} hours");
            $startAt = $rdt->Format(DBDatetime::ISO_DATETIME);
        } else if($this->lastBatchCount > 0) {
            $startAt = null;
            $batchStart = $this->batchStart + $this->batchLimit;
            $job = new SyncJob(
                $this->collectionName,
                $this->repeatHours,
                $this->batchLimit,// re-use batch limit
                $batchStart,// start at this page
            );
        }

        if($job) {
            Injector::inst()->get(QueuedJobService::class)->queueJob($job, $startAt);
        }
    }
}

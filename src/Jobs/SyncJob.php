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

    public function __construct(string $collectionName = '', int $repeatHours = 0)
    {
        $this->collectionName = trim($collectionName);
        $this->repeatHours = abs($repeatHours);
    }

    public function getTitle()
    {
        return _t(
            self::class . ".JOB_TITLE",
            "Sync collection to Typesense. Collection={collection}, Repeat={repeat}h",
            [
                'collection' => $this->collectionName,
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
            $exists = $client->collections[$collection->Name]->exists();
            $this->addMessage("Collection '{$collection->Name}' sync (exists=" . (int)$exists . ")");
            $response = $collection->syncWithTypesenseServer();
            $this->addMessage($response);
            $this->addMessage("Collection '{$collection->Name}' import");
            $collection->import();
            $this->addMessage("Collection '{$collection->Name}' import complete");

        } catch (\UnexpectedValueException $unexpectedValueException) {
            $this->addMessage("Failed to run: " . $unexpectedValueException->getMessage());
        } catch (\Exception $exception) {
            $this->addMessage("ERROR: " . $exception->getMessage());
        } finally {
            $this->isComplete = true;
        }
    }

    /**
     * Requeue job if configured
     */
    public function afterComplete() {
        if($this->repeatHours > 0) {
            $job = new SyncJob($this->collectionName, $this->repeatHours);
            $rdt = DBDatetime::now();
            $rdt->modify("+{$this->repeatHours} hours");
            QueuedJobService::singleton()->queueJob($job);
            Injector::inst()->get(QueuedJobService::class)->queueJob(
                $job,
                $rdt->Format(DBDatetime::ISO_DATETIME)
            );
        }
    }
}

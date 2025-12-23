<?php

namespace NSWDPC\Search\Typesense\Admin;

use NSWDPC\Search\Typesense\Jobs\SyncJob;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection as Collection;
use NSWDPC\Search\Typesense\Services\Logger;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Handle item requests for collection records
 */
class CollectionItemRequest extends GridFieldDetailForm_ItemRequest
{
    /**
     * @config
     */
    private static array $allowed_actions = [
        'edit',
        'view',
        'ItemEditForm'
    ];

    /**
     * Apply any extra actions to the modeladmin actions
     * @return FieldList
     */
    #[\Override]
    protected function getFormActions()
    {
        $actions = parent::getFormActions();
        $hasDelete = $actions->fieldByName('action_doDelete');
        $record = $this->getRecord();
        if (($record instanceof Collection) && ($cmsActions = $record->getCMSActions())) {
            foreach ($cmsActions as $action) {
                $actions->insertAfter(
                    ($hasDelete ? 'action_doDelete' : 'MajorActions'),
                    $action
                );
            }
        }

        return $actions;
    }

    /**
     * Tee up the collection sync via a queued job
     */
    public function doCollectionSync($data, $form)
    {
        try {

            $record = $this->getRecord();

            if (!($record instanceof Collection)) {
                throw new \UnexpectedValueException("Record is not a valid TypesenseSearchCollection");
            }

            if (!$record->isInDB()) {
                throw new \RuntimeException("Attempted reindex but doesn't exist in DB");
            }

            if (!Permission::check('EDITYPESENSE_COLLECTION_REINDEX')) {
                throw new \RuntimeException("User attempting reindex does not have that permission");
            }

            // Create then job
            $job = new SyncJob(
                $record->Name,
                0,// no repeat
                100,// batch limit
                0,// start at start
            );
            $startAt = null;
            $success = Injector::inst()->get(QueuedJobService::class)->queueJob($job, $startAt);

            if ($success) {
                $form->sessionMessage(
                    _t(
                        self::class . '.STARTED_SYNC_PROCESS',
                        "Reindex requested. The total time to index records depends on the size of the index"
                    ),
                    ValidationResult::TYPE_GOOD
                );
            } else {
                $form->sessionMessage(
                    _t(
                        self::class . '.FAILED_TO_START_SYNC_PROCESS',
                        "Could not start indexing process, please try again later"
                    ),
                    ValidationResult::TYPE_WARNING
                );
            }
        } catch (\Exception $exception) {
            Logger::log("Failed to start reindex: {$exception->getMessage()}", "NOTICE");
            $form->sessionMessage(
                _t(
                    self::class . '.ERROR_TO_START_SYNC_PROCESS',
                    "There was an error starting the indexing process, please try again later or request assistance."
                ),
                ValidationResult::TYPE_ERROR
            );
        }

        return $this->redirectAfterSave(false);
    }

}

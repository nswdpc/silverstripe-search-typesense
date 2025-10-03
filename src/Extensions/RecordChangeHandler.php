<?php

namespace NSWDPC\Search\Typesense\Extensions;

use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\SearchHandler;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Typesense\Exceptions\RequestMalformed;
use Typesense\Exceptions\ObjectNotFound;

/**
 * Provide record change handling for versioned and unversioned records
 * onAfterWrite: upsert document to Typesense for unversioned records, noop for versioned records
 * onAfterPublish: upsert document to Typesense for versioned records
 * onAfterPublishRecursive: upsert document to Typesense for unversioned records
 * onBeforeDelete: delete document from Typesense for unversioned records, noop for versioned records
 * onAfterUpublish: delete document from Typesense for versioned records
 * @extends \SilverStripe\ORM\DataExtension<static>
 */
class RecordChangeHandler extends DataExtension
{
    /**
     * Is this record versioned?
     */
    protected function isVersioned(DataObject $record): bool
    {
        return class_exists(Versioned::class) && $record->hasExtension(Versioned::class) && $record->hasStages();
    }

    /**
     * Handle record change handling exception logging
     */
    final protected function logExceptionError(string $message, ?\SilverStripe\ORM\DataObject $record = null, string $level = "NOTICE") {
        $id = "?";
        if(isset($record->ID)) {
            $id = (string)$record->ID;
        }
        Logger::log(sprintf($message, $id), $level);
    }

    public function onAfterWrite()
    {
        try {
            $record = null;
            /** @var \SilverStripe\ORM\DataObject $record */
            $record = $this->getOwner();
            if (!$this->isVersioned($record)) {
                SearchHandler::upsertToTypesense($record, true);
            }
        } catch (RequestMalformed $e) {
            $this->logExceptionError("onAfterWrite RequestMalformed upserting #%s to Typesense: " . $e->getMessage(), $record);
        } catch (\Exception $e) {
            $this->logExceptionError("onAfterWrite Exception upserting #%s to Typesense: " . $e->getMessage(), $record);
        }
    }

    public function onAfterPublish()
    {
        try {
            $record = null;
            /** @var \SilverStripe\ORM\DataObject $record */
            $record = $this->getOwner();
            SearchHandler::upsertToTypesense($record, true);
        } catch (RequestMalformed $e) {
            $this->logExceptionError("onAfterPublish RequestMalformed upserting #%s to Typesense: " . $e->getMessage(), $record);
        } catch (\Exception $e) {
            $this->logExceptionError("onAfterPublish Exception upserting #%s to Typesense: " . $e->getMessage(), $record);
        }
    }

    public function onAfterPublishRecursive()
    {
        try {
            $record = null;
            /** @var \SilverStripe\ORM\DataObject $record */
            $record = $this->getOwner();
            SearchHandler::upsertToTypesense($record, true);
        } catch (RequestMalformed $e) {
            $this->logExceptionError("onAfterPublishRecursive RequestMalformed upserting #%s to Typesense: " . $e->getMessage(), $record);
        } catch (\Exception $e) {
            $this->logExceptionError("onAfterPublishRecursive Exception upserting #%s to Typesense: " . $e->getMessage(), $record);
        }
    }

    public function onBeforeDelete()
    {
        try {
            $record = null;
            /** @var \SilverStripe\ORM\DataObject $record */
            $record = $this->getOwner();
            if (!$this->isVersioned($record)) {
                SearchHandler::deleteFromTypesense($record, true);
            }
        } catch (ObjectNotFound $e) {
            $this->logExceptionError("onBeforeDelete ObjectNotFound deleting #%s from Typesense: " . $e->getMessage(), $record);
        } catch (\Exception $e) {
            $this->logExceptionError("onBeforeDelete Exception deleting #%s from Typesense: " . $e->getMessage(), $record);
        }
    }

    public function onAfterUnpublish()
    {
        try {
            $record = null;
            /** @var \SilverStripe\ORM\DataObject $record */
            $record = $this->getOwner();
            SearchHandler::deleteFromTypesense($record, true);
        } catch (ObjectNotFound $e) {
            $this->logExceptionError("onAfterUpublish ObjectNotFound deleting #%s from Typesense: " . $e->getMessage(), $record);
        } catch (\Exception $e) {
            $this->logExceptionError("onAfterUpublish Exception deleting #%s from Typesense: " . $e->getMessage(), $record);
        }
    }
}

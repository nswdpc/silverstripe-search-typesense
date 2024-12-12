<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\DocumentUpdate;
use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\SearchHandler;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Provide record change handling for versioned and unversioned records
 * onAfterWrite: upsert document to Typesense for unversioned records, noop for versioned records
 * onAfterPublish: upsert document to Typesense for versioned records
 * onAfterPublishRecursive: upsert document to Typesense for unversioned records
 * onBeforeDelete: delete document from Typesense for unversioned records, noop for versioned records
 * onAfterUpublish: delete document from Typesense for versioned records
 *
 * @property DataObject|RecordChangeHandler $owner
 */
class RecordChangeHandler extends DataExtension {

    /**
     * Is this record versioned?
     */
    protected function isVersioned(DataObject $record): bool {
        return class_exists(Versioned::class) && $record->hasExtension(Versioned::class) && $record->hasStages();
    }

    public function onAfterWrite()
    {
        try {
            $record = $this->getOwner();
            if(!$this->isVersioned($record)) {
                SearchHandler::upsertToTypesense($record, true);
            }
        } catch (RequestMalformed $e) {
            Logger::log("onAfterWrite RequestMalformed upserting {$record->ID} to Typesense: " . $e->getMessage(), "NOTICE");
        } catch (\Exception $e) {
            Logger::log("onAfterWrite General exception upserting {$record->ID} to Typesense: " . $e->getMessage(), "NOTICE");
        }
    }

    public function onAfterPublish()
    {
        try {
            $record = $this->getOwner();
            SearchHandler::upsertToTypesense($record, true);
        } catch (RequestMalformed $e) {
            Logger::log("onAfterPublish RequestMalformed upserting {$record->ID} to Typesense: " . $e->getMessage(), "NOTICE");
        } catch (\Exception $e) {
            Logger::log("onAfterPublish general exception upserting {$record->ID} to Typesense: " . $e->getMessage(), "NOTICE");
        }
    }

    public function onAfterPublishRecursive()
    {
        try {
            $record = $this->getOwner();
            SearchHandler::upsertToTypesense($record, true);
        } catch (RequestMalformed $e) {
            Logger::log("onAfterPublishRecursive RequestMalformed upserting {$record->ID} to Typesense: " . $e->getMessage(), "NOTICE");
        } catch (\Exception $e) {
            Logger::log("onAfterPublishRecursive general exception upserting {$record->ID} to Typesense: " . $e->getMessage(), "NOTICE");
        }
    }

    public function onBeforeDelete()
    {
        try {
            $record = $this->getOwner();
            if(!$this->isVersioned($record)) {
                SearchHandler::deleteFromTypesense($record, true);
            }
        } catch (ObjectNotFound $e) {
            Logger::log("onBeforeDelete ObjectNotFound deleting {$record->ID} from Typesense: " . $e->getMessage(), "INFO");
        } catch (\Exception $e) {
            Logger::log("onBeforeDelete general exception deleting {$record->ID} from Typesense: " . $e->getMessage(), "NOTICE");
        }
    }

    public function onAfterUnpublish()
    {
        try {
            $record = $this->getOwner();
            SearchHandler::deleteFromTypesense($record, true);
        } catch (ObjectNotFound $e) {
            Logger::log("onAfterUpublish ObjectNotFound deleting {$record->ID} from Typesense: " . $e->getMessage(), "INFO");
        } catch (\Exception $e) {
            Logger::log("onAfterUpublish general exception deleting {$record->ID} from Typesense: " . $e->getMessage(), "NOTICE");
        }
    }
}

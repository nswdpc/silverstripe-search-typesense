<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\DocumentUpdate;

/**
 * Blocks upstream DocumentUpdate actions
 * NB: apply the \NSWDPC\Search\Typesense\Extensions\RecordChangeHandler extension to DataObjects that need upset/delete on write/delete
 */
class BlockDocumentUpdate extends DocumentUpdate
{
    #[\Override]
    public function onAfterWrite()
    {
    }

    #[\Override]
    public function onBeforeDelete()
    {
    }
}

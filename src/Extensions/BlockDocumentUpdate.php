<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\DocumentUpdate;

/**
 * Blocks upstream DocumentUpdate actions
 */
class BlockDocumentUpdate extends DocumentUpdate {

    #[\Override]
    public function onAfterWrite()
    {
    }

    #[\Override]
    public function onBeforeDelete()
    {
    }
}

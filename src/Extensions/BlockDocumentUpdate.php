<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\DocumentUpdate;

/**
 * Blocks upstream DocumentUpdate actions
 */
class BlockDocumentUpdate extends DocumentUpdate {

    public function onAfterWrite()
    {
        return;
    }

    public function onBeforeDelete()
    {
        return;
    }
}

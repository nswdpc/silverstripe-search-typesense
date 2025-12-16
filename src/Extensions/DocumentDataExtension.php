<?php

namespace NSWDPC\Search\Typesense\Extensions;

use NSWDPC\Search\Typesense\Services\TypesenseDocument;
use SilverStripe\Core\Extension;

/**
 * @extends \SilverStripe\Core\Extension<static>
 */
class DocumentDataExtension extends Extension
{
    /**
     * Return appropriate values based on the fields being indexed for Typesense
     * @return mixed[]
     */
    public function getTypesenseDocument(array $fields): array
    {
        // \NSWDPC\Search\Typesense\Services\Logger::log("DocumentDataExtension - getTypesenseDocument", "DEBUG");
        /** @var \SilverStripe\ORM\DataObject $owner */
        $owner = $this->getOwner();
        if(!$owner instanceof \SilverStripe\ORM\DataObject) {
            return [];
        } else {
            return TypesenseDocument::get($owner, $fields);
        }
    }

}

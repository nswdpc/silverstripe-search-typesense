<?php

namespace NSWDPC\Search\Typesense\Tests\Extensions;

use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use SilverStripe\Dev\SapphireTest;

class DocumentDataExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
    ];

    public function testGetTypesenseDocumentDelegatesToTypesenseDocument(): void
    {
        $record = TypesenseTestRecord::create(['Title' => 'A title']);
        $record->write();

        $document = $record->getTypesenseDocument([['name' => 'Title', 'type' => 'string']]);

        $this->assertSame('A title', $document['Title']);
        $this->assertSame((string) $record->ID, $document['id']);
    }
}

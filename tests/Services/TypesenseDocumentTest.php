<?php

namespace NSWDPC\Search\Typesense\Tests\Services;

use NSWDPC\Search\Typesense\Services\TypesenseDocument;
use NSWDPC\Search\Typesense\Tests\Fixtures\TypesenseTestRecord;
use SilverStripe\Dev\SapphireTest;

class TypesenseDocumentTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TypesenseTestRecord::class,
    ];

    public function testGetDefaultFields(): void
    {
        $fields = TypesenseDocument::getDefaultFields();
        $names = array_column($fields, 'name');
        $this->assertSame(
            ['id', 'ClassName', 'LastEdited', 'Created', 'TypesenseSearchResultData'],
            $names
        );
    }

    public function testGetResolvesFieldsInPrecedenceOrder(): void
    {
        $record = TypesenseTestRecord::create();
        $record->Title = 'Hello World';
        $record->Content = '<p>Some <strong>HTML</strong> content [shortcode]</p>';
        $record->PublishDate = '2024-01-15';
        $record->GenericValue = 'raw-generic';
        $record->CustomValue = 'raw-custom';
        $record->write();

        $fields = [
            ['name' => 'Title', 'type' => 'string'],
            ['name' => 'Content', 'type' => 'string'],
            ['name' => 'PublishDate', 'type' => 'int64'],
            ['name' => 'GenericValue', 'type' => 'string'],
            ['name' => 'CustomValue', 'type' => 'string'],
        ];

        $document = TypesenseDocument::get($record, $fields);

        // id is always overwritten with the string record ID
        $this->assertSame((string) $record->ID, $document['id']);

        // plain db field
        $this->assertSame('Hello World', $document['Title']);

        // HTMLText field is flattened to plain text and shortcodes are not processed
        $this->assertStringNotContainsString('<strong>', $document['Content']);
        $this->assertStringContainsString('[shortcode]', $document['Content']);

        // Date field typed int64 is coerced to a unix timestamp
        $this->assertIsInt($document['PublishDate']);
        $this->assertGreaterThan(0, $document['PublishDate']);

        // get{Field}() fallback is used when no getTypesenseValueFor{Field}() exists
        $this->assertSame('generic:raw-generic', $document['GenericValue']);

        // getTypesenseValueFor{Field}() takes precedence over get{Field}()/raw field access
        $this->assertSame('typesense:raw-custom', $document['CustomValue']);
    }

    public function testGetIncludesDefaultFields(): void
    {
        $record = TypesenseTestRecord::create();
        $record->Title = 'A record';
        $record->write();

        $document = TypesenseDocument::get($record, [['name' => 'Title', 'type' => 'string']]);

        $this->assertSame(TypesenseTestRecord::class, $document['ClassName']);
        $this->assertArrayHasKey('LastEdited', $document);
        $this->assertArrayHasKey('Created', $document);
    }

    public function testGetThrowsForFieldWithoutName(): void
    {
        $record = TypesenseTestRecord::create();
        $record->write();

        $this->expectException(\RuntimeException::class);
        TypesenseDocument::get($record, [['type' => 'string']]);
    }
}

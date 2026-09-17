<?php

namespace NSWDPC\Search\Typesense\Tests\Models;

use NSWDPC\Search\Typesense\Models\InstantSearch;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use SilverStripe\Dev\SapphireTest;

class InstantSearchTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testGetTypesenseNodesParsesOneNodePerLine(): void
    {
        $instantSearch = InstantSearch::create([
            'Nodes' => "https://one.example.com:443\nhttp://two.example.com:8108/path",
        ]);

        $nodes = $instantSearch->getTypesenseNodes();
        $this->assertSame([
            ['host' => 'one.example.com', 'port' => 443, 'protocol' => 'https', 'path' => ''],
            ['host' => 'two.example.com', 'port' => 8108, 'protocol' => 'http', 'path' => '/path'],
        ], $nodes);
    }

    public function testGetTypesenseNodesThrowsForMissingSchemeOrHost(): void
    {
        $instantSearch = InstantSearch::create(['Nodes' => 'not-a-url']);
        $this->expectException(\SilverStripe\ORM\ValidationException::class);
        $instantSearch->getTypesenseNodes();
    }

    public function testValidateTypesenseNodes(): void
    {
        $this->assertFalse(InstantSearch::create(['Nodes' => ''])->validateTypesenseNodes());
        $this->assertTrue(InstantSearch::create(['Nodes' => 'https://one.example.com:443'])->validateTypesenseNodes());
    }

    public function testGetCollectionNamePrefersLinkedCollectionOverStaticField(): void
    {
        $collection = TypesenseSearchCollection::create(['Name' => 'linked-collection', 'RecordClass' => InstantSearch::class]);
        $collection->write();

        $instantSearch = InstantSearch::create(['CollectionName' => 'static-name']);
        $instantSearch->CollectionID = $collection->ID;

        $this->assertSame('linked-collection', $instantSearch->getCollectionName());
    }

    public function testGetCollectionNameFallsBackToStaticFieldWhenNoCollectionLinked(): void
    {
        $instantSearch = InstantSearch::create(['CollectionName' => 'static-name']);
        $this->assertSame('static-name', $instantSearch->getCollectionName());
    }

    public function testProvidePermissionsListsExpectedCodes(): void
    {
        $codes = array_keys(InstantSearch::create()->providePermissions());
        $this->assertSame(
            [
                'INSTANTSEARCH_CONFIG_VIEW',
                'INSTANTSEARCH_CONFIG_EDIT',
                'INSTANTSEARCH_CONFIG_CREATE',
                'INSTANTSEARCH_CONFIG_DELETE',
            ],
            $codes
        );
    }

    public function testCanEditRequiresPermission(): void
    {
        // ensure no member from an earlier test is still logged in
        $this->logOut();

        $instantSearch = InstantSearch::create();
        $this->assertFalse($instantSearch->canEdit());

        $this->logInWithPermission('INSTANTSEARCH_CONFIG_EDIT');
        $this->assertTrue($instantSearch->canEdit());
    }

    public function testGetCmsFieldsScaffoldsExpectedCompositeFields(): void
    {
        $fields = InstantSearch::create()->getCMSFields();
        $this->assertNotNull($fields->dataFieldByName('Title'));
        $this->assertNotNull($fields->dataFieldByName('CollectionID'));
        $this->assertNotNull($fields->dataFieldByName('SearchScope'));
        $this->assertNotNull($fields->dataFieldByName('QueryBy'));
    }
}

<?php

namespace NSWDPC\Search\Typesense\Tests\Extensions;

use NSWDPC\Search\Typesense\Models\InstantSearch;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;

class InstantSearchExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();
    }

    public function testGetTypesenseBindMethodsReturnEmptyStringsByDefault(): void
    {
        $siteConfig = SiteConfig::create();
        $this->assertSame('', $siteConfig->getTypesenseBindToInputId());
        $this->assertSame('', $siteConfig->getTypesenseBindToParentId());
    }

    public function testUpdateCMSFieldsAddsInstantSearchDropdown(): void
    {
        $siteConfig = SiteConfig::create();
        $fields = $siteConfig->getCMSFields();
        $this->assertNotNull($fields->dataFieldByName('InstantSearchID'));
    }

    public function testGetCollectionNameReturnsEmptyStringWithoutAnEnabledInstantSearch(): void
    {
        $siteConfig = SiteConfig::create();
        $this->assertSame('', $siteConfig->getCollectionName());
    }

    public function testGetCollectionNameReadsFromLinkedInstantSearch(): void
    {
        $instantSearch = InstantSearch::create([
            'Title' => 'Site search',
            'Enabled' => true,
            'CollectionName' => 'my-collection',
            'Nodes' => 'https://search.example.com:443',
            'SearchScope' => '{"filter_by":"a:b"}',
        ]);
        $instantSearch->write();

        $siteConfig = SiteConfig::create();
        $siteConfig->InstantSearchID = $instantSearch->ID;

        $this->assertSame('my-collection', $siteConfig->getCollectionName());
    }

    public function testTypesenseInstantSearchReturnsNullWithoutAnEnabledInstantSearch(): void
    {
        $siteConfig = SiteConfig::create();
        $this->assertNull($siteConfig->TypesenseInstantSearch());
    }

    public function testTypesenseInstantSearchRendersFragmentWhenConfigured(): void
    {
        $instantSearch = InstantSearch::create([
            'Title' => 'Site search',
            'Enabled' => true,
            'CollectionName' => 'my-collection',
            'Nodes' => 'https://search.example.com:443',
            'SearchKey' => 'a-search-key',
            'SearchScope' => '{"filter_by":"a:b"}',
        ]);
        $instantSearch->write();

        $siteConfig = SiteConfig::create();
        $siteConfig->InstantSearchID = $instantSearch->ID;

        $result = $siteConfig->TypesenseInstantSearch();
        $this->assertInstanceOf(DBHTMLText::class, $result);
        $this->assertStringContainsString('data-instantsearch=', (string) $result);
    }
}

<?php

namespace NSWDPC\Search\Typesense\Tests\Models;

use NSWDPC\Search\Typesense\Models\TypesenseSearchOnlyKey;
use NSWDPC\Search\Typesense\Services\ClientManager;
use NSWDPC\Search\Typesense\Tests\Mocks\TestClientManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class TypesenseSearchOnlyKeyTest extends SapphireTest
{
    protected $usesDatabase = true;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestClientManager::create(), ClientManager::class);
        TestClientManager::reset();
    }

    // -- pure logic, no HTTP --

    public function testGetMaskedKeyValReturnsFirstFourChars(): void
    {
        $key = TypesenseSearchOnlyKey::create();
        $key->setField('KeyVal', 'abcdefgh');
        $this->assertSame('abcd', $key->getMaskedKeyVal());
    }

    public function testGetMaskedKeyValReturnsEmptyStringWhenNoKeyValSet(): void
    {
        $key = TypesenseSearchOnlyKey::create();
        $this->assertSame('', $key->getMaskedKeyVal());
    }

    public function testTitleWithMaskedKeyCombinesTitleAndMask(): void
    {
        $key = TypesenseSearchOnlyKey::create();
        $key->Title = 'My key';
        $key->setField('KeyVal', 'abcdefgh');
        $this->assertSame('My key - (abcd)', $key->TitleWithMaskedKey());
    }

    public function testSetKeyValInputSetsKeyValWhenGivenAValue(): void
    {
        $key = TypesenseSearchOnlyKey::create();
        $key->setKeyValInput('a-new-key');
        $this->assertSame('a-new-key', $key->getField('KeyVal'));
    }

    public function testSetKeyValInputLeavesExistingKeyValUnchangedWhenGivenNoValue(): void
    {
        $key = TypesenseSearchOnlyKey::create();
        $key->setField('KeyVal', 'existing-key');

        // neither an empty string nor null should overwrite the existing value
        $key->setKeyValInput('');
        $key->setKeyValInput();

        $this->assertSame('existing-key', $key->getField('KeyVal'));
    }

    public function testGetCmsFieldsExposesExpectedFieldsAndHidesRawKeyVal(): void
    {
        $key = TypesenseSearchOnlyKey::create();
        $fields = $key->getCmsFields();
        $this->assertNotNull($fields->dataFieldByName('Title'));
        $this->assertNotNull($fields->dataFieldByName('KeyValInput'));
        $this->assertNotNull($fields->dataFieldByName('IsEnabled'));
        $this->assertNull($fields->dataFieldByName('KeyVal'));
    }

    public function testProvidePermissionsListsExpectedCodes(): void
    {
        $codes = array_keys(TypesenseSearchOnlyKey::create()->providePermissions());
        $this->assertSame(
            [
                'TYPESENSE_KEY_VIEW',
                'TYPESENSE_KEY_EDIT',
                'TYPESENSE_KEY_CREATE',
                'TYPESENSE_KEY_DELETE',
            ],
            $codes
        );
    }

    // -- permissions --

    public function testCanViewRequiresPermission(): void
    {
        $this->logOut();
        $key = TypesenseSearchOnlyKey::create();
        $this->assertFalse($key->canView());

        $this->logInWithPermission('TYPESENSE_KEY_VIEW');
        $this->assertTrue($key->canView());
    }

    public function testCanEditRequiresPermission(): void
    {
        $this->logOut();
        $key = TypesenseSearchOnlyKey::create();
        $this->assertFalse($key->canEdit());

        $this->logInWithPermission('TYPESENSE_KEY_EDIT');
        $this->assertTrue($key->canEdit());
    }

    public function testCanCreateRequiresPermission(): void
    {
        $this->logOut();
        $key = TypesenseSearchOnlyKey::create();
        $this->assertFalse($key->canCreate());

        $this->logInWithPermission('TYPESENSE_KEY_CREATE');
        $this->assertTrue($key->canCreate());
    }

    public function testCanDeleteRequiresPermission(): void
    {
        $this->logOut();
        $key = TypesenseSearchOnlyKey::create();
        $this->assertFalse($key->canDelete());

        $this->logInWithPermission('TYPESENSE_KEY_DELETE');
        $this->assertTrue($key->canDelete());
    }

    public function testPermissionsAreGranular(): void
    {
        $this->logOut();
        $this->logInWithPermission('TYPESENSE_KEY_VIEW');
        $key = TypesenseSearchOnlyKey::create();
        $this->assertTrue($key->canView());
        $this->assertFalse($key->canEdit());
        $this->assertFalse($key->canCreate());
        $this->assertFalse($key->canDelete());
    }

    // -- validate(), HTTP-mocked --

    public function testValidatePassesForEmptyKeyWithoutAnHttpCall(): void
    {
        $key = TypesenseSearchOnlyKey::create(['Title' => 'No key yet', 'KeyVal' => '']);
        $result = $key->validate();
        $this->assertTrue($result->isValid());
        $this->assertSame(0, TestClientManager::$httpClient->getRequestCount());
    }

    public function testValidateFailsAndClearsKeyValWhenKeyNotFoundAtServer(): void
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => 'xyz', 'actions' => ['documents:search']],
            ],
        ]);

        $key = TypesenseSearchOnlyKey::create(['Title' => 'A key', 'KeyVal' => 'abcdefgh']);
        $result = $key->validate();
        $this->assertFalse($result->isValid());
        $this->assertSame('', $key->KeyVal);
    }

    public function testValidateFailsWhenKeyHasExtraActions(): void
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => 'abcd', 'actions' => ['documents:search', 'collections:create']],
            ],
        ]);

        $key = TypesenseSearchOnlyKey::create(['Title' => 'A key', 'KeyVal' => 'abcdefgh']);
        $result = $key->validate();
        $this->assertFalse($result->isValid());
        $this->assertSame('', $key->KeyVal);
    }

    public function testValidatePassesForAMatchingSearchOnlyKey(): void
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => 'abcd', 'actions' => ['documents:search']],
            ],
        ]);

        $key = TypesenseSearchOnlyKey::create(['Title' => 'A key', 'KeyVal' => 'abcdefgh']);
        $result = $key->validate();
        $this->assertTrue($result->isValid());
        $this->assertSame('abcdefgh', $key->KeyVal);
    }

    public function testWriteWithValidKeyPersistsRecordAsEnabled(): void
    {
        TestClientManager::$httpClient->queueJson(200, [
            'keys' => [
                ['value_prefix' => 'abcd', 'actions' => ['documents:search']],
            ],
        ]);

        $key = TypesenseSearchOnlyKey::create(['Title' => 'A key', 'KeyVal' => 'abcdefgh', 'IsEnabled' => true]);
        $key->write();

        $fetched = TypesenseSearchOnlyKey::get()->byID($key->ID);
        $this->assertNotNull($fetched);
        $this->assertSame('abcdefgh', $fetched->KeyVal);
        $this->assertTrue((bool) $fetched->IsEnabled);
    }
}

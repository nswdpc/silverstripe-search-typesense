<?php

namespace NSWDPC\Search\Typesense\Tests\Services;

use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection;
use NSWDPC\Search\Typesense\Services\FormCreator;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;

class FormCreatorTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        TypesenseSearchCollection::class,
    ];

    protected function createCollection(array $fields): TypesenseSearchCollection
    {
        $collection = TypesenseSearchCollection::create([
            'Name' => 'a-collection',
            'RecordClass' => TypesenseSearchCollection::class,
        ]);
        $collection->Metadata = json_encode([
            'name' => 'a-collection',
            'fields' => $fields,
        ]);
        return $collection;
    }

    public function testCreateForCollectionBasicSearchForm(): void
    {
        $collection = $this->createCollection([]);
        $form = FormCreator::createForCollection(Controller::curr(), $collection);
        $this->assertNotNull($form->Fields()->fieldByName('Search'));
        $this->assertNotNull($form->Actions()->fieldByName('action_doSearch'));
    }

    public function testCreateForCollectionAdvancedSearchFormScaffoldsFieldsByType(): void
    {
        $collection = $this->createCollection([
            ['name' => 'Title', 'type' => 'string'],
            ['name' => 'Views', 'type' => 'int32'],
            ['name' => 'BigCount', 'type' => 'int64'],
            ['name' => 'Rating', 'type' => 'float'],
            ['name' => 'Active', 'type' => 'bool'],
            ['name' => 'Hidden', 'type' => 'string', 'index' => false],
            ['name' => 'Unsupported', 'type' => 'geopoint'],
        ]);

        $form = FormCreator::createForCollection(Controller::curr(), $collection, 'SearchForm', true);
        $fields = $form->Fields();

        $this->assertInstanceOf(TextField::class, $fields->fieldByName('Title'));
        $this->assertInstanceOf(DropdownField::class, $fields->fieldByName('Active'));
        $this->assertNotNull($fields->fieldByName('Views'));
        $this->assertNotNull($fields->fieldByName('BigCount'));
        $this->assertNotNull($fields->fieldByName('Rating'));

        // un-indexed and unsupported-type fields are not scaffolded
        $this->assertNull($fields->fieldByName('Hidden'));
        $this->assertNull($fields->fieldByName('Unsupported'));
    }
}

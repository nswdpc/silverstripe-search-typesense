<?php

namespace NSWDPC\Search\Typesense\Tests\Models;

use NSWDPC\Search\Typesense\Models\TypesenseSearchResult;
use SilverStripe\Dev\SapphireTest;

class TypesenseSearchResultTest extends SapphireTest
{
    public function testMagicGetSetIsset(): void
    {
        $result = TypesenseSearchResult::create(['Title' => 'A title']);
        $this->assertTrue(isset($result->Title));
        $this->assertSame('A title', $result->Title);
        $this->assertFalse(isset($result->Missing));
        $this->assertNull($result->Missing);

        $result->Abstract = 'An abstract';
        $this->assertSame('An abstract', $result->Abstract);
    }

    public function testToArray(): void
    {
        $data = ['Title' => 'A title', 'Link' => '/a-link'];
        $result = TypesenseSearchResult::create($data);
        $this->assertSame($data, $result->toArray());
    }

    public function testHighlight(): void
    {
        $result = TypesenseSearchResult::create();
        $this->assertSame('', $result->Highlight());
        $result->setHighlight('<mark>match</mark>');
        $this->assertSame('<mark>match</mark>', $result->Highlight());
    }

    public function testLabelListReturnsNullWhenLabelsNotArray(): void
    {
        $result = TypesenseSearchResult::create(['Labels' => 'not-an-array']);
        $this->assertNull($result->LabelList());
    }

    public function testLabelListFiltersAndMapsStringAndArrayLabels(): void
    {
        $result = TypesenseSearchResult::create([
            'Labels' => [
                'Simple Label',
                '',
                ['Name' => 'complex', 'Link' => '/complex', 'Title' => 'Complex Label'],
            ],
        ]);

        $labels = $result->LabelList()->toArray();
        $this->assertCount(2, $labels);
        $this->assertSame('Simple Label', $labels[0]->Name);
        $this->assertSame('Simple Label', $labels[0]->Title);
        $this->assertSame('complex', $labels[1]->Name);
        $this->assertSame('/complex', $labels[1]->Link);
        $this->assertSame('Complex Label', $labels[1]->Title);
    }
}

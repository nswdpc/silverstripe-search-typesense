<?php

namespace NSWDPC\Search\Typesense\Tests\Models;

use NSWDPC\Search\Typesense\Models\Result;
use NSWDPC\Search\Typesense\Models\TypesenseSearchResult;
use SilverStripe\Dev\SapphireTest;

class ResultTest extends SapphireTest
{
    protected function makeResult(array $document): Result
    {
        return Result::create($document, [], [], 0, []);
    }

    public function testMagicGetSetIsset(): void
    {
        $result = $this->makeResult(['Title' => 'A title']);
        $this->assertTrue(isset($result->Title));
        $this->assertSame('A title', $result->Title);
        $this->assertFalse(isset($result->Missing));

        $result->Extra = 'value';
        $this->assertSame('value', $result->Extra);
    }

    public function testTypesenseSearchResultReturnsNullWhenNoData(): void
    {
        $result = $this->makeResult(['Title' => 'A title']);
        $this->assertNull($result->TypesenseSearchResult());
    }

    public function testTypesenseSearchResultReturnsInstanceWhenDataPresent(): void
    {
        $result = $this->makeResult([
            'Title' => 'A title',
            'TypesenseSearchResultData' => ['Title' => 'Result title'],
        ]);

        $typesenseResult = $result->TypesenseSearchResult();
        $this->assertInstanceOf(TypesenseSearchResult::class, $typesenseResult);
        $this->assertSame('Result title', $typesenseResult->Title);
    }

    public function testGetTemplateNameReturnsNullWithoutClassName(): void
    {
        $result = $this->makeResult(['Title' => 'A title']);
        $this->assertNull($result->getTemplateName());
    }

    public function testGetTemplateNameIsBasedOnClassName(): void
    {
        $result = $this->makeResult(['ClassName' => 'My\\App\\Record']);
        $this->assertSame('My\\App\\Record_TypesenseSearchResult', $result->getTemplateName());
    }
}

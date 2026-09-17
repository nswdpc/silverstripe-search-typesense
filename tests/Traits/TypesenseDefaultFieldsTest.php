<?php

namespace NSWDPC\Search\Typesense\Tests\Traits;

use NSWDPC\Search\Typesense\Models\TypesenseSearchResult;
use NSWDPC\Search\Typesense\Traits\TypesenseDefaultFields;
use SilverStripe\Dev\SapphireTest;

class TypesenseDefaultFieldsTest extends SapphireTest
{
    public function testDefaultResultIsEmpty(): void
    {
        $user = new class {
            use TypesenseDefaultFields;
        };

        $result = $user->getTypesenseSearchResult();
        $this->assertInstanceOf(TypesenseSearchResult::class, $result);
        $this->assertSame([], $result->toArray());
    }

    public function testGetTypesenseSearchResultDataReturnsResultAsArray(): void
    {
        $user = new class {
            use TypesenseDefaultFields;

            public function getTypesenseSearchResult(): TypesenseSearchResult
            {
                return TypesenseSearchResult::create(['Title' => 'A title']);
            }
        };

        $this->assertSame(['Title' => 'A title'], $user->getTypesenseSearchResultData());
    }
}

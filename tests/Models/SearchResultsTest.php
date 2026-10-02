<?php

namespace NSWDPC\Search\Typesense\Tests\Models;

use NSWDPC\Search\Typesense\Models\SearchResults;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;

class SearchResultsTest extends SapphireTest
{
    public function testGetLimitItemsIsAlwaysFalse(): void
    {
        $results = SearchResults::create(ArrayList::create());
        $this->assertFalse($results->getLimitItems());
        $results->setLimitItems(true);
        $this->assertFalse($results->getLimitItems());
    }

    public function testResultData(): void
    {
        $results = SearchResults::create(ArrayList::create());
        $results->setResultData(['found' => 5, 'hits' => ['a', 'b'], 'search_time_ms' => 2], ['hits']);

        $resultData = $results->getResultData();
        $this->assertArrayNotHasKey('hits', $resultData);
        $this->assertSame(5, $results->getResultValue('found'));
        $this->assertSame(2, $results->getResultValue('search_time_ms'));
        $this->assertNull($results->getResultValue('missing'));
    }
}

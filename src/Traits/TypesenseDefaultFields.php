<?php

namespace NSWDPC\Search\Typesense\Traits;

use NSWDPC\Search\Typesense\Models\TypesenseSearchResult;

/**
 * Use this trait on classes indexed in Typesense
 */
trait TypesenseDefaultFields
{

    /**
     * Classes using this trait should populate TypesenseSearchResult with data required to render a result
     */
    final public function getTypesenseSearchResultData(): array {
        return $this->getTypesenseSearchResult()->toArray();
    }

    /**
     * Classes using this trait should populate TypesenseSearchResult with data required to render a result
     */
    public function getTypesenseSearchResult(): TypesenseSearchResult {
        return TypesenseSearchResult::create();
    }

}

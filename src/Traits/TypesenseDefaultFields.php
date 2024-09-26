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


    /**
    - { name: TypesenseResultPublicDate, type: int64, optional: true, index: false }
    - { name: TypesenseResultTitle, type: string, index: false }
    - { name: TypesenseResultLink, type: string, index: false }
    - { name: TypesenseResultImageURL, type: string, optional: true, index: false }
    - { name: TypesenseResultImageAlt, type: string, optional: true, index: false }
    - { name: TypesenseResultPrimaryLabel, type: string, optional: true, index: false }
    - { name: TypesenseResultLabels, type: 'string[]', optional: true, index: false }
    - { name: TypesenseResultExtract, type: string, optional: true, index: false }
    - { name: TypesenseResultInfo, type: string, optional: true, index: false }
    **/

}

<?php

namespace NSWDPC\Search\Typesense\Models;

use SilverStripe\ORM\PaginatedList;

/**
 * A sub-class of paginated list, with option for storing
 * search result information returned by typesense
 */
class SearchResults extends PaginatedList {

    /**
     * Typesense results are pre-paginated
     */
    protected $limitItems = false;

    /**
     * Array of key=>value pairs returns in the Typesense results
     */
    protected $resultData = [];

    public function setResultData(array $resultData, array $removeKeys = []): static {
        foreach($removeKeys as $removeKey) {
            unset($resultData[$removeKey]);
        }

        $this->resultData = $resultData;
        return $this;
    }

    public function getResultData(): array {
        return $this->resultData;
    }

    public function getResultValue(string $key): mixed {
        return $this->resultData[$key] ?? null;
    }

    #[\Override]
    public function getLimitItems()
    {
        return false;
    }

    /**
     * @inheritdoc
     * This value is always false, as Typesense returns pre-paginated results
     */
    #[\Override]
    public function setLimitItems($limit)
    {
        $this->limitItems = false;
        return $this;
    }
}

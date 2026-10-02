<?php

namespace NSWDPC\Search\Typesense\Extensions;

use NSWDPC\Search\Typesense\Models\TypesenseSearchOnlyKey;
use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\ScopedSearch;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

/**
 * Extension applied to models that can apply a search scope and/or scoped API
 * key for searching
 * @property ?string $SearchKey
 * @property ?string $SearchScope
 * @extends \SilverStripe\ORM\DataExtension<(\NSWDPC\Search\Typesense\Models\InstantSearch & static)>
 * @property bool $UseSelectedKey
 * @property int $SearchOnlyKeyID
 * @method \NSWDPC\Search\Typesense\Models\TypesenseSearchOnlyKey SearchOnlyKey()
 */
class ScopedSearchExtension extends DataExtension
{
    /**
     * Provide a search scope + search only key field
     */
    private static array $db = [
        'SearchKey' => 'Varchar(255)',// search-only API key (deprecated)
        'SearchScope' => 'Text',// JSON text of search scope
        'UseSelectedKey' => 'Boolean' // whether to use SearchOnlyKey.KeyVal or not
    ];

    private static array $has_one = [
        'SearchOnlyKey' => TypesenseSearchOnlyKey::class
    ];

    /**
     * Validate the model
     */
    public function validate(ValidationResult $result)
    {

        // validate the scope
        $searchScope = trim((string)$this->getOwner()->SearchScope);
        if ($searchScope === '') {
            $result->addError(
                _t(
                    static::class . ".SEARCH_SCOPE_INVALID_EMPTY",
                    "Please provide a search scope."
                )
            );
        } elseif (!ScopedSearch::validateSearchScope($searchScope)) {
            $result->addError(
                _t(
                    static::class . ".SEARCH_SCOPE_INVALID_JSON",
                    "The search scope provided is not valid JSON"
                )
            );
        }

    }

    /**
     * Get the key used as the search-only key
     */
    public function getTypesenseSearchOnlyKey(): string
    {
        $owner = $this->getOwner();
        if ($owner->UseSelectedKey == 1) {
            // requested to use selected key
            $searchOnlyKey = $owner->SearchOnlyKey();
            // retrieve the key value only is present and enabled
            $keyVal = $searchOnlyKey && $searchOnlyKey->IsEnabled == 1 ? $searchOnlyKey->KeyVal : '';
            $searchKey = $keyVal;
        } else {
            // use the stored key
            $searchKey = Environment::getEnv('TYPESENSE_SEARCH_KEY');
        }

        return trim($searchKey ?? '');
    }

    /**
     * Get a scoped search key for the owner dataobject
     */
    public function getTypesenseScopedSearchKey(): ?string
    {

        $searchKey = $this->getTypesenseSearchOnlyKey();
        // check if valid
        if ($searchKey === '') {
            Logger::log("No Typesense search or API key defined - cannot create a scoped search key", "NOTICE");
            return null;
        }

        $searchScope = trim($this->getOwner()->SearchScope ?? '');
        if (!ScopedSearch::validateSearchScope($searchScope)) {
            // ensure a default scope is set, if invalid
            $searchScope = json_encode(ScopedSearch::getDefaultScope());
        }

        try {
            return ScopedSearch::getScopedApiKey($searchKey, ScopedSearch::getDecodedSearchScope($searchScope));
        } catch (\Exception $exception) {
            Logger::log("Scope provided is invalid: " . $exception->getMessage(), "NOTICE");
            return null;
        }
    }

}

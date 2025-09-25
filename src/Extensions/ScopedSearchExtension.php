<?php

namespace NSWDPC\Search\Typesense\Extensions;

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
 */
class ScopedSearchExtension extends DataExtension {

    /**
     * Provide a search scope + search only key field
     */
    private static array $db = [
        'SearchKey' => 'Varchar(255)',// search-only API key
        'SearchScope' => 'Text'
    ];

    /**
     * Validate the model
     */
    public function validate(ValidationResult $result) {

        // validate the scope
        $searchScope = trim((string)$this->getOwner()->SearchScope);
        if($searchScope !== '' && !ScopedSearch::validateSearchScope($searchScope)) {
            $result->addError(
                _t(
                    static::class . ".SEARCH_SCOPE_INVALID_JSON",
                    "The search scope provided is not valid JSON"
                )
            );
        }

        // validate the key entered - not currently in use
        /*
        $searchKey = trim((string)$this->getOwner()->SearchKey);
        if($searchKey !== '' && !ScopedSearch::validateSearchOnlyKey($searchKey)) {
            $this->getOwner()->SearchKey = '';// reset on invalid
            $result->addError(
                _t(
                    static::class . ".SEARCH_KEY_INVALID",
                    "The search key provided is invalid. It must exist at the Typesense server and have a single action 'documents:search'"
                )
            );
        }
        */
    }

    /**
     * Get a scoped search key for the owner dataobject
     */
    public function getTypesenseScopedSearchKey(): ?string {

        // prefer the stored key
        $searchKey = Environment::getEnv('TYPESENSE_SEARCH_KEY');
        if(!$searchKey) {
            // try the one entered in the UI
            $searchKey = $this->getOwner()->SearchKey;
        } else {
        }

        // check if valid
        if(!$searchKey) {
            Logger::log("No Typesense search or API key defined - cannot create a scoped search key", "NOTICE");
            return null;
        }

        $searchScope = trim($this->getOwner()->SearchScope ?? '');
        if(!ScopedSearch::validateSearchScope($searchScope)) {
            // ensure a default scope is set, if invalid
            $searchScope = ScopedSearch::getDefaultScope();
        }

        try {
            return ScopedSearch::getScopedApiKey($searchKey, ScopedSearch::getDecodedSearchScope($searchScope));
        } catch (\Exception $exception) {
            Logger::log("Scope provided is invalid: " . $exception->getMessage(), "NOTICE");
            return null;
        }
    }

}

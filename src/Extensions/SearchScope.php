<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\Typesense;
use KevinGroeger\CodeEditorField\Forms\CodeEditorField;
use NSWDPC\Search\Typesense\Services\Logger;
use NSWDPC\Search\Typesense\Services\SearchHandler;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ValidationResult;

/**
 * Extension applied to models that can apply a search scope and/or scoped API
 * key for searching
 */
class SearchScope extends DataExtension {

    /**
     * Provide a search scope + search only key field
     */
    private static array $db = [
        'SearchKey' => 'Varchar(255)',// search-only API key
        'SearchScope' => 'Text'
    ];

    /**
     * Get the default scope
     */
    public static function getDefaultScope(): array {
        return [
            'include_fields' => 'Title,TypesenseSearchResultData'
        ];
    }

    /**
     * Get a JSON editor field for editing the scoped search in a nice way
     */
    public static function getSearchScopeField(): CodeEditorField {
        return CodeEditorField::create(
            'SearchScope',
            _t(static::class . '.INSTANT_SEARCH_SEARCHSCOPE', 'Provide the search scope as JSON'),
        )->setDescription(
            _t(static::class . '.INSTANT_SEARCH_SEARCHSCOPE_NOTES', "Review the Typesense documentation 'Generate Scoped Search Key' for help in setting this value.")
        )->setMode('ace/mode/json')
        ->setTheme('ace/theme/dracula');
    }

    public static function getSearchKeyField(): TextField {
        return TextField::create(
            'SearchKey',
            _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY', 'Search-only key')
        )->setDescription(
            _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY_WARNING', "Use a Typesense search-only API key with the single action 'documents:search'. This will be checked and validated on save.")
        );
    }

    /**
     * Validate the model
     */
    public function validate(ValidationResult $result) {
        $searchScope = trim((string)$this->getOwner()->SearchScope);
        if($searchScope !== '') {
            $searchScope = static::validateSearchScope($searchScope);
            if($searchScope === '') {
                $result->addError(
                    _t(
                        static::class . ".SEARCH_SCOPE_INVALID_JSON",
                        "The search scope provided is not valid JSON"
                    )
                );
            }
        }

        // validate the key entered
        $searchKey = trim((string)$this->getOwner()->SearchKey);
        if($searchKey !== '') {
            $searchKey = static::validateSearchOnlyKey($searchKey);
            if($searchKey === '') {
                $this->getOwner()->SearchKey = '';// reset on invalid
                $result->addError(
                    _t(
                        static::class . ".SEARCH_KEY_INVALID",
                        "The search key provided is invalid. It must exist at the Typesense server and have a single action 'documents:search'"
                    )
                );
            }
        }
    }

    /**
     * Validate the search key provided
     */
    public static function validateSearchOnlyKey(string $searchKey): string {
        try {
            if($searchKey === '') {
                return '';
            }
            $client = Typesense::client();
            $results = $client->keys->retrieve();
            $keyFound = false;
            // print_r($results);
            foreach($results['keys'] as $key) {
                if(!str_starts_with($searchKey, $key['value_prefix'])) {
                    // ignore this key returned
                    continue;
                }

                $keyFound = true;
                // check the prefixed key's actions
                // scoped keys can only contain document:search
                // https://typesense.org/docs/28.0/api/api-keys.html#generate-scoped-search-key
                if(!isset($key['actions']) || $key['actions'] != ['documents:search']) {
                    throw new \InvalidArgumentException("Invalid key actions value");
                }
            }

            if(!$keyFound) {
                throw new \InvalidArgumentException("The key entered does not exist");
            }

        } catch (\Exception $e) {
            // on error clear the value
            $searchKey = '';
        }

        return $searchKey;
    }

    /**
     * Get the decoded search scope, empty array if invalid
     */
    public static function getDecodedSearchScope(string $searchScope): array {
        try {
            $scope = json_decode($searchScope, true, 512, JSON_THROW_ON_ERROR);
            if(is_array($scope)) {
                return $scope;
            }
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Validate and return the search scope, if valid will pretty print the JSON value
     * back into SearchScope value
     * @param string $searchScope a string in JSON format
     * @return string the validated JSON string or an empty string if invalid
     */
    public static function validateSearchScope(string $searchScope): string {
        $searchScope = trim($searchScope);
        if($searchScope !== '') {
            try {
                $scope = static::getDecodedSearchScope($searchScope);
                if(is_array($scope)) {
                    return json_encode($scope, JSON_PRETTY_PRINT);
                }
            } catch (\JsonException $jsonException) {
                $searchScope = '';
            }
        } else {
            $searchScope = '';
        }
        return $searchScope;
    }

    /**
     * Given a search-only API key and a scope generate a scoped API key
     */
    public static function getScopedApiKey(string $searchOnlyKey, array $searchScope): ?string {
        $client = Typesense::client();
        $scopedKey = $client->keys->generateScopedSearchKey($searchOnlyKey, $searchScope);
        if(is_string($scopedKey)) {
            return $scopedKey;
        } else {
            return null;
        }
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
            Logger::log("SearchScope using field key: " . get_class($this->getOwner()), "INFO");
        } else {
            Logger::log("SearchScope using env key: " . get_class($this->getOwner()), "INFO");
        }

        // check if valid
        if(!$searchKey) {
            Logger::log("SearchScope: No Typesense search or API key defined - cannot create a scoped search key", "NOTICE");
            return null;
        }

        $searchKey = static::validateSearchOnlyKey($searchKey);
        if($searchKey === '') {
            Logger::log("The search key in use is invalid. Please provide a search only key with a single action of 'documents:search'.", "NOTICE");
            return null;
        }

        $searchScope = static::validateSearchScope(trim($this->getOwner()->SearchScope ?? ''));
        if($searchScope == '') {
            // ensure a default scope is set, if invalid
            $searchScope = static::getDefaultScope();
        }
        return static::getScopedApiKey($searchKey, static::getDecodedSearchScope($searchScope));
    }

}

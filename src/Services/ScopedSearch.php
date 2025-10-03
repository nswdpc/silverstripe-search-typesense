<?php

namespace NSWDPC\Search\Typesense\Services;

use ElliotSawyer\SilverstripeTypesense\Typesense;
use KevinGroeger\CodeEditorField\Forms\CodeEditorField;
use SilverStripe\Forms\TextField;

/**
 * Collection of methods to assist with scoped search handling
 */
abstract class ScopedSearch
{
    /**
     * Get a JSON editor field for editing the scoped search in a nice way
     */
    public static function getSearchScopeField(): CodeEditorField
    {
        return CodeEditorField::create(
            'SearchScope',
            _t(static::class . '.INSTANT_SEARCH_SEARCHSCOPE', 'Provide the search scope as JSON'),
        )->setDescription(
            _t(static::class . '.INSTANT_SEARCH_SEARCHSCOPE_NOTES', "Review the Typesense documentation 'Generate Scoped Search Key' for help in setting this value.")
        )->setMode('ace/mode/json')
        ->setTheme('ace/theme/dracula');
    }

    /**
     * Get the search key field
     */
    public static function getSearchKeyField(): TextField
    {
        return TextField::create(
            'SearchKey',
            _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY', 'Search-only key')
        )->setDescription(
            _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY_WARNING', "Use a Typesense search-only API key with the single action 'documents:search'. This will be checked and validated on save.")
        );
    }

    /**
     * Get the default scope
     */
    public static function getDefaultScope(): array
    {
        return [
            'include_fields' => 'Title,TypesenseSearchResultData'
        ];
    }

    /**
     * Validate the search key provided
     * @note this requires a stored TYPESENSE_API_KEY with keys:list permission
     * @throws \Exception
     */
    public static function validateSearchOnlyKey(string $searchKey): bool
    {
        try {
            if ($searchKey === '') {
                throw new \InvalidArgumentException("Empty key provided");
            }

            $manager = new ClientManager();
            $client = $manager->getConfiguredClient();
            $results = $client->keys->retrieve();
            $keyFound = false;
            // print_r($results);
            foreach ($results['keys'] as $key) {
                if (!str_starts_with($searchKey, (string) $key['value_prefix'])) {
                    // ignore this key returned
                    continue;
                }

                $keyFound = true;
                // check the prefixed key's actions
                // scoped keys can only contain document:search
                // https://typesense.org/docs/28.0/api/api-keys.html#generate-scoped-search-key
                if (!isset($key['actions']) || $key['actions'] != ['documents:search']) {
                    throw new \RuntimeException("Invalid key actions value");
                }
            }

            if (!$keyFound) {
                throw new \RuntimeException("The key entered does not exist");
            }

            return true;

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get the decoded search scope, null if invalid
     */
    public static function getDecodedSearchScope(string $searchScope): ?array
    {
        $searchScope = trim($searchScope);
        if($searchScope === '') {
            return [];
        }

        $scope = json_decode($searchScope, true, 512, JSON_THROW_ON_ERROR);
        if (is_array($scope)) {
            return $scope;
        } else {
            return null;
        }
    }

    /**
     * Validate and return the search scope, if valid will pretty print the JSON value
     * back into SearchScope value
     * @param string $searchScope a string in JSON format
     */
    public static function validateSearchScope(string $searchScope): bool
    {
        try {
            if ($searchScope === '') {
                // empty scope is valid
                return true;
            }

            $scope = static::getDecodedSearchScope($searchScope);
            if (is_array($scope)) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            Logger::log("Error: " . $exception->getMessage(), "INFO");
            return false;
        }
    }

    /**
     * Given a search-only API key and a scope generate a scoped API key
     */
    public static function getScopedApiKey(string $searchOnlyKey, array $searchScope): string
    {
        $manager = new ClientManager();
        $client = $manager->getConfiguredClient();
        return $client->keys->generateScopedSearchKey($searchOnlyKey, $searchScope);
    }
}

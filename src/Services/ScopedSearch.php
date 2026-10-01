<?php

namespace NSWDPC\Search\Typesense\Services;

use NSWDPC\Search\Typesense\Models\TypesenseSearchOnlyKey;
use KevinGroeger\CodeEditorField\Forms\CodeEditorField;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CheckboxField;

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
    public static function getSearchKeyField(): ToggleCompositeField
    {

        $keyField = DropdownField::create(
            'SearchOnlyKeyID',
            _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY', 'Search-only key'),
            TypesenseSearchOnlyKey::get()->filter(['IsEnabled' => 1])->map('ID', 'TitleWithMaskedKey')->toArray()
        )->setDescription(
            _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY_WARNING', "Select a Typesense search-only API key")
        )->setEmptyString('');

        $selectionField = CheckboxField::create(
            'UseSelectedKey',
            _t(static::class . '.INSTANT_SEARCH_USE_THIS_KEY', 'Use the selected key ')
        )->setDescription(
            _t(static::class . '.INSTANT_SEARCH_USE_THIS_KEY_HELP', 'Overrides system-provided search-only key'),
        );

        return ToggleCompositeField::create(
            'SearchKeyToggle',
            _t(static::class . '.SEARCH_KEY', 'Key'),
            [
                $keyField,
                $selectionField
            ]
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

            $manager = Injector::inst()->get(ClientManager::class);
            $client = $manager->getConfiguredClient();
            $results = $client->keys->retrieve();
            $keyFound = false;
            // print_r($results);
            foreach ($results['keys'] as $key) {

                // if it's not the same key identifier..
                if (!str_starts_with($searchKey, (string) $key['value_prefix'])) {
                    // ignore this key
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

        } catch (\Exception $exception) {
            Logger::log("Failed to validate key with error: {$exception->getMessage()}", "NOTICE");
            return false;
        }
    }

    /**
     * Get the decoded search scope, null if invalid
     */
    public static function getDecodedSearchScope(string $searchScope): ?array
    {
        $searchScope = trim($searchScope);
        if ($searchScope === '') {
            return [];
        }

        $scope = json_decode($searchScope, true, 512, JSON_THROW_ON_ERROR);
        if (is_array($scope)) {
            return $scope;
        }

        return null;
    }

    /**
     * Return whether the passed search scope is valid. An empty search scope is not valid
     * @param string $searchScope a string in JSON format
     */
    public static function validateSearchScope(string $searchScope): bool
    {
        try {
            if ($searchScope === '') {
                // empty scope is NOT valid
                return false;
            }

            $scope = static::getDecodedSearchScope($searchScope);
            return is_array($scope);
        } catch (\Exception $exception) {
            Logger::log("Error: " . $exception->getMessage(), "INFO");
            return false;
        }
    }

    /**
     * Given a search-only API key and a scope generate a scoped API key
     * @param string $searchOnlyKey a key with no other permissions besides `documents:search`
     * @param array $searchScope a non empty scope for the scoped API key.
     */
    public static function getScopedApiKey(string $searchOnlyKey, array $searchScope): string
    {
        if ($searchScope === []) {
            throw new \RuntimeException("A scoped API key requires a non-empty search scope");
        }

        $manager = Injector::inst()->get(ClientManager::class);
        $client = $manager->getConfiguredClient();
        return $client->keys->generateScopedSearchKey($searchOnlyKey, $searchScope);
    }
}

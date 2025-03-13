<?php

namespace NSWDPC\Search\Typesense\Extensions;

use NSWDPC\Search\Typesense\Services\InstantSearch;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Extension applied to models that can provide instantsearch interface
 * e.g elemental content blocks providing a search interface
 */
class InstantSearchExtension extends DataExtension {

    private static array $db = [
        'UseInstantSearch' => 'Boolean',
        'InstantSearchPrompt' => 'Varchar(255)',
        'InstantSearchKey' => 'Varchar(255)'
    ];

    private static array $defaults = [
        'UseInstantSearch' => 0
    ];

    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldsToTab(
            'Root.InstantSearch',
            [
                CheckboxField::create(
                    'UseInstantSearch',
                    _t(static::class . '.USE_INSTANT_SEARCH', 'Use "Instant Search"')
                ),
                TextField::create(
                    'InstantSearchKey',
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY', 'The public key used for "Instant Search"')
                )->setDescription(
                    _t(static::class . '.INSTANT_SEARCH_PUBLIC_KEY_WARNING', 'Use a Typesense "Search-only API Key" only, here. This will be included publicly in the website.')
                ),
                TextField::create(
                    'InstantSearchPrompt',
                    _t(static::class . '.INSTANT_SEARCH_PROMPT', 'Field prompt for "Instant Search"')
                )
            ]
        );
    }

    /**
     * Return a unique ID for the searchbox and
     * Users of this extension can implement their own value
     * E.g elements can use the getAnchor() method return value
     */
    public function getTypesenseUniqID(): string {
        return bin2hex(random_bytes(4));
    }

    /**
     * template method for getting the element's unique ID in the DOM
     */
    public function TypesenseUniqID(): string {
        return $this->getOwner()->getTypesenseUniqID();
    }

    /**
     * Template method to process and render the instantsearch interface and requirements
     * See: https://github.com/typesense/typesense-instantsearch-adapter?tab=readme-ov-file#with-instantsearchjs
     * Templates using typesense instantsearch should add the include to their template:
     * <% include NSWDPC/Search/Typesense/InstantSearchResults %>
     * The include will call this method
     */
    public function TypesenseInstantSearch(): void {
        if($this->getOwner()->UseInstantSearch == 0) {
            return;
        }
        $collection = $this->getOwner()->getCollection();
        $collectionName = $collection ? $collection->Name : '';
        if($collectionName === '') {
            return;
        }
        $id = $this->getOwner()->getTypesenseUniqID();
        $apiKey = $this->getOwner()->InstantSearchKey;
        $nodes = [];
        $queryBy = 'Title';
        $serverExtra = [
            'cacheSearchResultsForSeconds' => 120
        ];
        $searchBox = "#{$id}-searchbox";
        $hitBox = "#{$id}-hits";
        $spec = [
            'Configuration' => InstantSearch::createConfiguration($apiKey, $queryBy),
            'CollectionName' => $collectionName,
            'Searchbox' => $searchBox,
            'Hitbox' => $hitBox
        ];

        // Add instantsearch
        InstantSearch::provide($id, $spec);
    }
}

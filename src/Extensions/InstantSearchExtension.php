<?php

namespace NSWDPC\Search\Typesense\Extensions;

use ElliotSawyer\SilverstripeTypesense\Collection;
use NSWDPC\Search\Typesense\Models\InstantSearch;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ValidationException;

/**
 * Extension applied to models that can provide instantsearch interface
 * e.g elemental content blocks providing a search interface
 */
class InstantSearchExtension extends DataExtension {

    private static array $has_one = [
        'InstantSearch' => InstantSearch::class
    ];

    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldsToTab(
            'Root.InstantSearch',
            [
                DropdownField::create(
                    'InstantSearchID',
                    _t(static::class . '.INSTANT_SEARCH_CHOOSE', 'Choose an Instant Search configuration to use'),
                    InstantSearch::get()->filter(['Enabled' => 1])->sort(['Title' => 'ASC'])->map('ID','Title')
                )->setEmptyString('')
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
     * The model using this extension needs to provide an input ID to bind to
     */
    public function getTypesenseBindToInputId(): string {
        return "";
    }

    /**
     * The model using this extension needs to provide the ID of a parent used to locate
     * the hits box
     */
    public function getTypesenseBindToParentId(): string {
        return "";
    }

    /**
     * template method for getting the element's unique ID in the DOM
     */
    public function TypesenseUniqID(): string {
        return $this->getOwner()->getTypesenseUniqID();
    }

    /**
     * Return the chosen InstantSearch config model for this model
     */
    protected function getInstantSearch(): ?InstantSearch {
        $instantSearch = $this->getOwner()->InstantSearch();
        if(!$instantSearch || !$instantSearch->isInDB() || !$instantsearch->Enabled) {
            return null;
        } else {
            return $instantSearch;
        }
    }

    /**
     * Get the collection name
     * If the model using this extension has a getCollection method, this can be used to provide
     * the collection name
     */
    public function getCollectionName(): string {
        $instantSearch = $this->getInstantSearch();
        if(!$instantSearch) {
            return '';
        }

        // get from chosen config model
        $collectionName = $instantSearch->getCollectionName();
        // if not set, try to get from owner model
        if(!$collectionName && $this->getOwner()->hasMethod('getCollection')) {
            $collection = $this->getOwner()->getCollection();
            if($collection && ($collection instanceof Collection)) {
                $collectionName = $collection->Name;
            }
        }

        return (string)$collectionName;
    }

    /**
     * Template method to process and render the instantsearch interface and requirements
     * See: https://github.com/typesense/typesense-instantsearch-adapter?tab=readme-ov-file#with-instantsearchjs
     * Templates using typesense instantsearch should add the include to their template:
     * <% include NSWDPC/Search/Typesense/InstantSearchResults %>
     * The include will call this method
     */
    public function TypesenseInstantSearch(): ?DBHTMLText {
        $instantSearch = $this->getInstantSearch();
        if($instantSearch) {
            return $instantSearch->provideInstantSearchFor($this->getOwner());
        } else {
            return null;
        }
    }
}

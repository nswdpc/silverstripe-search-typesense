<?php

namespace NSWDPC\Search\Typesense\Services;

use Codem\Utilities\HTML5\NumberField;
use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection as Collection;
use NSWDPC\Search\Forms\Forms\AdvancedSearchForm;
use NSWDPC\Search\Forms\Forms\SearchForm;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TextField;

/**
 * Form creator based on configuration
 */
abstract class FormCreator
{
    /**
     * Return a form based on config, with fields scaffolder
     */
    public static function createForCollection(Controller $controller, Collection $collection, string $name = "SearchForm", bool $useAdvancedSearch = false): SearchForm|AdvancedSearchForm
    {

        if (!$useAdvancedSearch) {
            //basic search form
            $form = SearchForm::create(
                $controller,
                'SearchForm',
                FieldList::create([
                    TextField::create(
                        'Search',
                        _t(self::class . '.SEARCH_TERM_LABEL', 'Search for')
                    )
                ]),
                FieldList::create([
                    FormAction::create(
                        'doSearch',
                        'Search'
                    )
                ])
            );
        } else {
            $form = AdvancedSearchForm::create(
                $controller,
                'SearchForm',
                FieldList::create([]),
                FieldList::create([
                    FormAction::create(
                        'doSearch',
                        'Search'
                    )
                ])
            );
            $form = self::getFields($collection, $form);
        }

        return $form;
    }

    protected static function getFields(Collection $collection, AdvancedSearchForm $form): AdvancedSearchForm
    {
        $fields = $collection->getCollectionFields();
        $fieldList = FieldList::create();
        foreach ($fields as $field) {
            if (!is_object($field)) {
                continue;
            }

            if (($formField = self::getField($field)) instanceof \SilverStripe\Forms\FormField) {
                $fieldList->push($formField);
            }
        }

        $form = $form->setFields($fieldList);
        return $form;
    }

    /**
     * Given a field object, return a FormField for the form suitable for that data type
     * @param object $field an object representing a  field within the 'fields' configuration of a Typesense Collection
     */
    protected static function getField(object $field): ?FormField
    {
        if (isset($field->index) && !$field->index) {
            // do not show unindexed fields
            return null;
        }

        return match ($field->type) {
            'string' => self::getTextField($field),
            'int32' => self::getIntField($field),
            'int64' => self::getBigIntField($field),
            'float' => self::getFloatField($field),
            'bool' => self::getBoolField($field),
            default => null // not yet supported
        };
    }

    /**
     * Get a text field
     */
    public static function getTextField(object $field): TextField
    {
        return TextField::create(
            $field->name,
            $field->name
        );
    }

    /**
     * Get a field for int32
     */
    public static function getIntField(object $field): NumberField
    {
        return NumberField::create(
            $field->name,
            $field->name
        );
    }

    /**
     * Get a field for int64
     */
    public static function getBigIntField(object $field): NumberField
    {
        return NumberField::create(
            $field->name,
            $field->name
        );
    }

    /**
     * Get a field for float
     */
    public static function getFloatField(object $field): NumberField
    {
        return NumberField::create(
            $field->name,
            $field->name
        );
    }

    /**
     * Get a boolean field
     */
    public static function getBoolField(object $field): DropdownField
    {
        return DropdownField::create(
            $field->name,
            $field->name,
            [
                'true' => _t(self::class . ".YES", "Yes"),
                'false' => _t(self::class . ".NO", "No"),
            ]
        )->setEmptyString('');
    }
}

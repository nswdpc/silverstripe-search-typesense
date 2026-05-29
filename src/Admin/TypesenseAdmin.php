<?php

namespace NSWDPC\Search\Typesense\Admin;

use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection as Collection;
use NSWDPC\Search\Typesense\Models\InstantSearch;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;

class TypesenseAdmin extends ModelAdmin
{
    private static string $url_segment = 'typesense-search';

    private static string $menu_title = 'Typesense Search';

    private static array $managed_models = [
        Collection::class,
        InstantSearch::class
    ];

    private static string $menu_icon_class = 'font-icon-dashboard';


    /**
     * @inheritdoc
     */
    #[\Override]
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        if ($this->modelClass == Collection::class) {
            $field = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));
            if ($field instanceof GridField) {
                $fieldConfig = $field->getConfig();
                $detailFormComponent = $fieldConfig->getComponentByType(GridFieldDetailForm::class);
                if ($detailFormComponent) {
                    $detailFormComponent->setItemRequestClass(CollectionItemRequest::class);
                }
            }
        }

        return $form;
    }

}

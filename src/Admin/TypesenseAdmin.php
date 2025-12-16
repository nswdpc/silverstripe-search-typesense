<?php

namespace NSWDPC\Search\Typesense\Admin;

use NSWDPC\Search\Typesense\Models\TypesenseSearchCollection as Collection;
use NSWDPC\Search\Typesense\Models\InstantSearch;
use SilverStripe\Admin\ModelAdmin;

class TypesenseAdmin extends ModelAdmin
{
    private static string $url_segment = 'typesense-search';

    private static string $menu_title = 'Typesense Search';

    private static array $managed_models = [
        Collection::class,
        InstantSearch::class
    ];

    private static string $menu_icon_class = 'font-icon-dashboard';
}

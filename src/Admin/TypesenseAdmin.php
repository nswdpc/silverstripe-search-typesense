<?php

namespace NSWDPC\Search\Typesense\Admin;

use NSWDPC\Search\Typesense\Models\InstantSearch;
use SilverStripe\Admin\ModelAdmin;

class TypesenseAdmin extends ModelAdmin
{
    private static $url_segment = 'typesense-search';
    private static $menu_title = 'Typesense Search';
    private static $managed_models = [
        InstantSearch::class
    ];
    private static $menu_icon_class = 'font-icon-dashboard';
}

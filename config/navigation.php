<?php

use App\Services\Navigation\Constants\CentralMenu;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Registrars\CentralMainMenuRegistrar;
use App\Services\Navigation\Registrars\TenantMainMenuRegistrar;

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Registrars
    |--------------------------------------------------------------------------
    | The registrars that should be loaded via the NavigationServiceProvider
    | Each registrar should register one or more menus (or sections/items/children of a registered menu)
    */
    'registrars' => [
        TenantMainMenuRegistrar::class,
        CentralMainMenuRegistrar::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Sort Order
    |--------------------------------------------------------------------------
    |
    | You may override the sortOrder of any navigation section, item, or child in
    | the entire application by specifying their keys here.
    |
    | Each entry will have a 'sortOrder' property, and then (optionally) the keys for any children
    | which follow the same pattern.
    |
    | Sorting is relevant only to the parent 'item' for each i.e.
    | The order of child-item within item X has no effect in the order of child-item within item Y
    |
    | In a sidebar menu, for example, this is how ordered sections/items would look:
    |
    | Main Menu
    | ├── (10) Dashboard
    | ├── (20) CMS
    | │   ├── (30) Pages
    | │   └── (40) Layouts
    | ├── (30) CRM
    | │   ├── (1000) Customers
    | │   └── (1001) Forms
    | └── Account (200)
    |     └── (20) Settings
    |         ├── (1) Profile
    |         └── (2) Security
    */
    'sorting' => [
        TenantMenu::MENU_MAIN => [
            TenantMenu::MENU_MAIN_SECTION_DASHBOARD => [
                'sortOrder' => 0,
                'dashboard' => ['sortOrder' => 0],
            ],
            TenantMenu::MENU_MAIN_SECTION_ADMIN => [
                'sortOrder' => 90,
                TenantMenu::MENU_MAIN_SECTION_ADMIN_LABEL => ['sortOrder' => 0],
                TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS => [
                    'sortOrder' => 10,
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_VIEW => ['sortOrder' => 0],
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_CREATE => ['sortOrder' => 1],
                ],
            ],
            TenantMenu::MENU_MAIN_SECTION_ACCOUNT => [
                'sortOrder' => 100,
                TenantMenu::MENU_MAIN_SECTION_ACCOUNT_LABEL => ['sortOrder' => 0],
                TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS => [
                    'sortOrder' => 1,
                    TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_PROFILE => ['sortOrder' => 0],
                    TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_SECURITY => ['sortOrder' => 1],
                ],
            ],
        ],
        CentralMenu::MENU_MAIN => [

        ],
    ],
];

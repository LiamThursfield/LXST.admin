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
            TenantMenu::MENU_MAIN_SECTION_CMS => [
                'sortOrder' => 20,
                TenantMenu::MENU_MAIN_SECTION_CMS_LABEL => ['sortOrder' => 0],
                TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_PAGES => [
                    'sortOrder' => 10,
                    TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_PAGES_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS => [
                    'sortOrder' => 70,
                    TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_MENUS => [
                    'sortOrder' => 75,
                    TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_MENUS_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES => [
                    'sortOrder' => 80,
                    TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS => [
                    'sortOrder' => 90,
                    TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS_VIEW => ['sortOrder' => 0],
                ],
            ],
            TenantMenu::MENU_MAIN_SECTION_CRM => [
                'sortOrder' => 30,
                TenantMenu::MENU_MAIN_SECTION_CRM_LABEL => ['sortOrder' => 0],
                TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_CONTACTS => [
                    'sortOrder' => 10,
                    TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_CONTACTS_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_FORMS => [
                    'sortOrder' => 20,
                    TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_FORMS_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS => [
                    'sortOrder' => 25,
                    TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS => [
                    'sortOrder' => 30,
                    TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS_VIEW => ['sortOrder' => 0],
                ],
            ],
            TenantMenu::MENU_MAIN_SECTION_FILES => [
                'sortOrder' => 60,
                TenantMenu::MENU_MAIN_SECTION_FILES_ITEM_FILES => ['sortOrder' => 0],
            ],
            TenantMenu::MENU_MAIN_SECTION_ADMIN => [
                'sortOrder' => 90,
                TenantMenu::MENU_MAIN_SECTION_ADMIN_LABEL => ['sortOrder' => 0],
                TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS => [
                    'sortOrder' => 10,
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_VIEW => ['sortOrder' => 0],
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_CREATE => ['sortOrder' => 1],
                ],
                TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS => [
                    'sortOrder' => 20,
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_GENERAL => ['sortOrder' => 0],
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_THEME => ['sortOrder' => 1],
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_MAIL => ['sortOrder' => 2],
                    TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_INTEGRATIONS => ['sortOrder' => 3],
                ],
            ],
            TenantMenu::MENU_MAIN_SECTION_TRACKER => [
                'sortOrder' => 95,
                TenantMenu::MENU_MAIN_SECTION_TRACKER_LABEL => ['sortOrder' => 0],
                TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TODAY => ['sortOrder' => 5],
                TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TASKS => [
                    'sortOrder' => 10,
                    TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TASKS_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES => [
                    'sortOrder' => 20,
                    TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES_VIEW => ['sortOrder' => 0],
                ],
                TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS => [
                    'sortOrder' => 30,
                    TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS_VIEW => ['sortOrder' => 0],
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
            CentralMenu::MENU_MAIN_SECTION_DASHBOARD => ['sortOrder' => 0],
            CentralMenu::MENU_MAIN_SECTION_TENANTS => ['sortOrder' => 10],
            CentralMenu::MENU_MAIN_SECTION_ACCOUNT => ['sortOrder' => 100],
        ],
    ],
];

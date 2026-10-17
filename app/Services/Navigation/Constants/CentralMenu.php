<?php

namespace App\Services\Navigation\Constants;

/**
 * This class contains constants that define the keys for:
 * - Menus
 * - MenuSections
 * - MenuItems
 * of the central application.
 *
 * These will be registered by default, but keys are not limited to those that are defined in this class.
 * Constants are typically only defined when a menu/section/item is likely to be added to by different modules/providers
 *
 * The current - core - central menu is as follows (this does not include the child items in each section):
 * Main Menu
 * ├── Dashboard
 * ├── Tenants
 * ├── Admin
 * │   └── Users
 * └── Account
 *     └── Settings
 */
class CentralMenu
{
    public const string MENU_MAIN = 'central_main';

    public const string MENU_MAIN_SECTION_DASHBOARD = 'dashboard';

    public const string MENU_MAIN_SECTION_TENANTS = 'tenants';

    public const string MENU_MAIN_SECTION_TENANTS_ITEM_TENANTS = 'tenants';

    public const string MENU_MAIN_SECTION_ADMIN = 'admin';

    public const string MENU_MAIN_SECTION_ADMIN_LABEL = 'admin-label';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS = 'users';

    public const string MENU_MAIN_SECTION_ACCOUNT = 'account';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS = 'settings';
}

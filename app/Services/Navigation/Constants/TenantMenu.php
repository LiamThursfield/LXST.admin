<?php

namespace App\Services\Navigation\Constants;

/**
 * This class contains constants that define the keys for:
 * - Menus
 * - MenuSections
 * - MenuItems
 * of the tenant application.
 *
 * These will be registered by default, but keys are not limited to those that are defined in this class.
 * Constants are typically only defined when a menu/section/item is likely to be added to by different modules/providers
 *
 * The current - core - tenant menu is as follows (this does not include the child items in each section):
 * Main Menu
 * ├── Dashboard
 * ├── Admin
 * │   └── Users
 * └── Account
 *     └── Settings
 */
class TenantMenu
{
    public const string MENU_MAIN = 'tenant_main';

    public const string MENU_MAIN_SECTION_DASHBOARD = 'dashboard';

    public const string MENU_MAIN_SECTION_ADMIN = 'admin';

    public const string MENU_MAIN_SECTION_ADMIN_LABEL = 'admin-label';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS = 'users';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS_CREATE = 'create';

    public const string MENU_MAIN_SECTION_ACCOUNT = 'account';

    public const string MENU_MAIN_SECTION_ACCOUNT_LABEL = 'account-label';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS = 'settings';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_PROFILE = 'profile';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_SECURITY = 'security';
}

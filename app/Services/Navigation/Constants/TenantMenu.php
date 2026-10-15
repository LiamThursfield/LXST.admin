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
 * ├── CRM
 * │   ├── Contacts
 * │   ├── Forms
 * │   ├── Submissions
 * │   └── Organisation Units
 * ├── Admin
 * │   └── Users
 * ├── Tracker
 * │   ├── Today
 * │   ├── Tasks
 * │   ├── Categories
 * │   └── Holidays
 * └── Account
 *     └── Settings
 */
class TenantMenu
{
    public const string MENU_MAIN = 'tenant_main';

    public const string MENU_MAIN_SECTION_DASHBOARD = 'dashboard';

    public const string MENU_MAIN_SECTION_CRM = 'crm';

    public const string MENU_MAIN_SECTION_CRM_LABEL = 'crm-label';

    public const string MENU_MAIN_SECTION_CRM_ITEM_CONTACTS = 'contacts';

    public const string MENU_MAIN_SECTION_CRM_ITEM_CONTACTS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CRM_ITEM_FORMS = 'forms';

    public const string MENU_MAIN_SECTION_CRM_ITEM_FORMS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS = 'form-submissions';

    public const string MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS = 'organisation-units';

    public const string MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_ADMIN = 'admin';

    public const string MENU_MAIN_SECTION_ADMIN_LABEL = 'admin-label';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS = 'users';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS_CREATE = 'create';

    public const string MENU_MAIN_SECTION_TRACKER = 'tracker';

    public const string MENU_MAIN_SECTION_TRACKER_LABEL = 'tracker-label';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES = 'categories';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES_VIEW = 'view';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_TODAY = 'today';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_TASKS = 'tasks';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_TASKS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS = 'holidays';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_ACCOUNT = 'account';

    public const string MENU_MAIN_SECTION_ACCOUNT_LABEL = 'account-label';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS = 'settings';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_PROFILE = 'profile';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_SECURITY = 'security';
}

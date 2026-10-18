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
 * ├── CMS
 * │   ├── Pages
 * │   ├── Layouts
 * │   ├── Menus
 * │   ├── Templates
 * │   └── Redirects
 * ├── CRM
 * │   ├── Contacts
 * │   ├── Forms
 * │   ├── Submissions
 * │   └── Organisation Units
 * ├── DJ
 * │   ├── Library (Tracks, Artists, Libraries)
 * │   ├── Gigs
 * │   ├── Requests
 * │   └── Stats
 * ├── Tracker
 * │   ├── Today
 * │   ├── Tasks
 * │   ├── Categories
 * │   └── Holidays
 * ├── Tools
 * │   └── Files
 * ├── Admin
 * │   ├── Users
 * │   └── Site Settings
 * └── Account
 *     └── Settings
 */
class TenantMenu
{
    public const string MENU_MAIN = 'tenant_main';

    public const string MENU_MAIN_SECTION_DASHBOARD = 'dashboard';

    public const string MENU_MAIN_SECTION_CMS = 'cms';

    public const string MENU_MAIN_SECTION_CMS_LABEL = 'cms-label';

    public const string MENU_MAIN_SECTION_CMS_ITEM_PAGES = 'pages';

    public const string MENU_MAIN_SECTION_CMS_ITEM_PAGES_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS = 'layouts';

    public const string MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CMS_ITEM_MENUS = 'menus';

    public const string MENU_MAIN_SECTION_CMS_ITEM_MENUS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES = 'templates';

    public const string MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS = 'redirects';

    public const string MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS_VIEW = 'view';

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

    public const string MENU_MAIN_SECTION_DJ = 'dj';

    public const string MENU_MAIN_SECTION_DJ_LABEL = 'dj-label';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY = 'library';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_TRACKS = 'tracks';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_ARTISTS = 'artists';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_LIBRARIES = 'libraries';

    public const string MENU_MAIN_SECTION_TRACKER = 'tracker';

    public const string MENU_MAIN_SECTION_TRACKER_LABEL = 'tracker-label';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES = 'categories';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES_VIEW = 'view';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_TODAY = 'today';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_TASKS = 'tasks';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_TASKS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS = 'holidays';

    public const string MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_TOOLS = 'tools';

    public const string MENU_MAIN_SECTION_TOOLS_LABEL = 'tools-label';

    public const string MENU_MAIN_SECTION_TOOLS_ITEM_FILES = 'file-manager';

    public const string MENU_MAIN_SECTION_ADMIN = 'admin';

    public const string MENU_MAIN_SECTION_ADMIN_LABEL = 'admin-label';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS = 'users';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS_VIEW = 'view';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_USERS_CREATE = 'create';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS = 'site-settings';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_GENERAL = 'general';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_INTEGRATIONS = 'integrations';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_EMAIL_PREVIEWS = 'email-previews';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_THEME = 'theme';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_MAIL = 'mail';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_API_TOKENS = 'api-tokens';

    public const string MENU_MAIN_SECTION_ACCOUNT = 'account';

    public const string MENU_MAIN_SECTION_ACCOUNT_LABEL = 'account-label';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS = 'settings';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_PROFILE = 'profile';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_SECURITY = 'security';
}

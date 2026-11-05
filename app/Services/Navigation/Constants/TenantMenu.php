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
 * ├── Bookings
 * │   ├── Calendar
 * │   ├── Bookings
 * │   ├── Timetable
 * │   ├── Waitlist
 * │   ├── Messages
 * │   ├── Message log
 * │   ├── Services
 * │   ├── Service categories
 * │   ├── Staff
 * │   ├── Resources
 * │   ├── Rota
 * │   ├── Time off
 * │   └── Staff hours
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
 * │   ├── Library (Songs, Artists, Library tracks, Library artists, Libraries, Matching)
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

    public const string MENU_MAIN_SECTION_BOOKINGS = 'bookings';

    public const string MENU_MAIN_SECTION_BOOKINGS_LABEL = 'bookings-label';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_CALENDAR = 'calendar';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_BOOKINGS = 'bookings';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_SESSIONS = 'sessions';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_WAITLIST = 'waitlist';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_MESSAGES = 'messages';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_MESSAGE_LOG = 'message-log';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_SERVICES = 'services';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_SERVICE_CATEGORIES = 'service-categories';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_STAFF = 'staff';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_RESOURCES = 'resources';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_ROTA = 'rota';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_TIME_OFF = 'time-off';

    public const string MENU_MAIN_SECTION_BOOKINGS_ITEM_STAFF_HOURS = 'staff-hours';

    public const string MENU_MAIN_SECTION_CMS = 'cms';

    public const string MENU_MAIN_SECTION_CMS_LABEL = 'cms-label';

    public const string MENU_MAIN_SECTION_CMS_ITEM_PAGES = 'pages';

    public const string MENU_MAIN_SECTION_CMS_ITEM_PAGES_VIEW = 'view';

    public const string MENU_MAIN_SECTION_CMS_ITEM_SAVED_COMPONENTS = 'saved-components';

    public const string MENU_MAIN_SECTION_CMS_ITEM_SAVED_COMPONENTS_VIEW = 'view';

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

    public const string MENU_MAIN_SECTION_DJ_ITEM_GIGS = 'gigs';

    public const string MENU_MAIN_SECTION_DJ_ITEM_SETLISTS = 'setlists';

    public const string MENU_MAIN_SECTION_DJ_ITEM_HISTORY = 'history';

    public const string MENU_MAIN_SECTION_DJ_ITEM_REQUESTS = 'requests';

    public const string MENU_MAIN_SECTION_DJ_ITEM_ANNUAL_DROP = 'annual-drop';

    public const string MENU_MAIN_SECTION_DJ_ITEM_SETTINGS = 'dj-settings';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_SONGS = 'songs';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_CANONICAL_ARTISTS = 'canonical-artists';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_TRACKS = 'tracks';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_ARTISTS = 'artists';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_LIBRARIES = 'libraries';

    public const string MENU_MAIN_SECTION_DJ_ITEM_LIBRARY_MATCHING = 'matching';

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

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_INDUSTRY = 'industry';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_THEME = 'theme';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_MAIL = 'mail';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_MESSAGING = 'messaging';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_API_TOKENS = 'api-tokens';

    public const string MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_DJ = 'site-settings-dj';

    public const string MENU_MAIN_SECTION_ACCOUNT = 'account';

    public const string MENU_MAIN_SECTION_ACCOUNT_LABEL = 'account-label';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS = 'settings';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_PROFILE = 'profile';

    public const string MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_SECURITY = 'security';
}

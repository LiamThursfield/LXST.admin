<?php

namespace App\Services\Navigation\Registrars;

use App\Enums\TenantModule;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Authorisation\Enums\FileManagerPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Authorisation\Enums\TrackerPermission;
use App\Services\Navigation\Collections\Menu;
use App\Services\Navigation\Collections\MenuSection;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Data\MenuChildItem;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Enums\MenuItemType;
use App\Services\Navigation\Exceptions\DuplicateMenuKeyException;
use App\Services\Navigation\Exceptions\DuplicateMenuSectionKeyException;
use App\Services\Navigation\Exceptions\MenuKeyDoesNotExistException;
use App\Services\Navigation\Helpers\NavigationConfig;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Support\Collection;

class TenantMainMenuRegistrar implements MenuRegistrar
{
    protected string $menuKey = TenantMenu::MENU_MAIN;

    /**
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     * @throws DuplicateMenuKeyException
     */
    public function register(NavigationRegistry $registry): void
    {
        $registry->addMenu(new Menu, $this->menuKey);

        $this->registerDashboardSection($registry);
        $this->registerCmsSection($registry);
        $this->registerCrmSection($registry);
        $this->registerDjSection($registry);
        $this->registerTrackerSection($registry);
        $this->registerToolsSection($registry);
        $this->registerAdminSection($registry);
        $this->registerAccountSection($registry);
    }

    /**
     * Register the Dashboard section of the tenant Main Menu
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerDashboardSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_DASHBOARD;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: 'dashboard',
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, 'dashboard']),
                        label: 'navigation.dashboard',
                        type: MenuItemType::LINK,
                        to: 'admin.dashboard',
                        icon: 'i-lucide-house',
                        exact: true,
                    ),
                ]),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }

    /**
     * Register the CMS section of the tenant Main Menu
     * Every item requires the CMS module, so the whole section is hidden when it is switched off
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerCmsSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_CMS;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CMS_LABEL,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_LABEL]),
                        label: 'navigation.cms',
                        type: MenuItemType::LABEL,
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_PAGES,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_PAGES]),
                        label: 'navigation.pages',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-file-text',
                        activePatterns: ['admin.cms.pages.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_PAGES_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_PAGES, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_PAGES_VIEW]),
                                label: 'navigation.view_pages',
                                to: 'admin.cms.pages.index',
                            )->gate(CmsPermission::ViewContent),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS]),
                        label: 'navigation.layouts',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-panels-top-left',
                        activePatterns: ['admin.cms.layouts.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_LAYOUTS_VIEW]),
                                label: 'navigation.view_layouts',
                                to: 'admin.cms.layouts.index',
                            )->gate(CmsPermission::ViewStructure),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_MENUS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_MENUS]),
                        label: 'navigation.cms_menus',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-menu',
                        activePatterns: ['admin.cms.menus.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_MENUS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_MENUS, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_MENUS_VIEW]),
                                label: 'navigation.view_cms_menus',
                                to: 'admin.cms.menus.index',
                            )->gate(CmsPermission::ViewStructure),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES]),
                        label: 'navigation.templates',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-layout-template',
                        activePatterns: ['admin.cms.templates.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_TEMPLATES_VIEW]),
                                label: 'navigation.view_templates',
                                to: 'admin.cms.templates.index',
                            )->gate(CmsPermission::ViewStructure),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS]),
                        label: 'navigation.redirects',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-signpost',
                        activePatterns: ['admin.cms.redirects.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS, TenantMenu::MENU_MAIN_SECTION_CMS_ITEM_REDIRECTS_VIEW]),
                                label: 'navigation.view_redirects',
                                to: 'admin.cms.redirects.index',
                            )->gate(CmsPermission::ViewStructure),
                        ]),
                    ),
                ])->each(fn (MenuItem $item) => $item->module(TenantModule::Cms)),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }

    /**
     * Register the CRM section of the tenant Main Menu
     * Every item requires the CRM module, so the whole section is hidden when it is switched off
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerCrmSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_CRM;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CRM_LABEL,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_LABEL]),
                        label: 'navigation.crm',
                        type: MenuItemType::LABEL,
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_CONTACTS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_CONTACTS]),
                        label: 'navigation.contacts',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-contact',
                        activePatterns: ['admin.crm.contacts.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_CONTACTS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_CONTACTS, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_CONTACTS_VIEW]),
                                label: 'navigation.view_contacts',
                                to: 'admin.crm.contacts.index',
                            )->gate(CrmPermission::ViewContacts),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_FORMS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_FORMS]),
                        label: 'navigation.forms',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-clipboard-list',
                        activePatterns: ['admin.crm.forms.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_FORMS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_FORMS, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_FORMS_VIEW]),
                                label: 'navigation.view_forms',
                                to: 'admin.crm.forms.index',
                            )->gate(CrmPermission::ViewForms),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS]),
                        label: 'navigation.form_submissions',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-inbox',
                        activePatterns: ['admin.crm.form-submissions.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_SUBMISSIONS_VIEW]),
                                label: 'navigation.view_form_submissions',
                                to: 'admin.crm.form-submissions.index',
                            )->gate(CrmPermission::ViewFormSubmissions),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS]),
                        label: 'navigation.organisation_units',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-building-2',
                        activePatterns: ['admin.crm.organisation-units.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS, TenantMenu::MENU_MAIN_SECTION_CRM_ITEM_ORGANISATION_UNITS_VIEW]),
                                label: 'navigation.view_organisation_units',
                                to: 'admin.crm.organisation-units.index',
                            )->gate(CrmPermission::ViewOrganisationUnits),
                        ]),
                    ),
                ])->each(fn (MenuItem $item) => $item->module(TenantModule::Crm)),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }

    /**
     * Register the DJ section of the tenant Main Menu
     * Every item requires the DJ module, so the whole section is hidden when it is switched off.
     * Gigs, Requests and Stats are added here as they're built
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerDjSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_DJ;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_DJ_LABEL,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_DJ_LABEL]),
                        label: 'navigation.dj',
                        type: MenuItemType::LABEL,
                    ),
                    (new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_DJ_ITEM_LIBRARY,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_DJ_ITEM_LIBRARY]),
                        label: 'navigation.library',
                        type: MenuItemType::LINK,
                        to: 'admin.dj.libraries.index',
                        icon: 'i-lucide-library',
                        activePatterns: ['admin.dj.libraries.*'],
                    ))->gate(DjPermission::ViewLibrary),
                ])->each(fn (MenuItem $item) => $item->module(TenantModule::Dj)),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }

    /**
     * Register the Tracker section of the tenant Main Menu
     * Every item requires the Tracker module, so the whole section is hidden when it is switched off
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerTrackerSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_TRACKER;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_TRACKER_LABEL,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_LABEL]),
                        label: 'navigation.tracker',
                        type: MenuItemType::LABEL,
                    ),
                    (new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TODAY,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TODAY]),
                        label: 'navigation.today',
                        type: MenuItemType::LINK,
                        to: 'admin.tracker.today',
                        icon: 'i-lucide-calendar-check',
                        exact: true,
                    ))->gate(TrackerPermission::ViewTasks),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TASKS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TASKS]),
                        label: 'navigation.tasks',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-list-checks',
                        activePatterns: ['admin.tracker.tasks.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TASKS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TASKS, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_TASKS_VIEW]),
                                label: 'navigation.view_tasks',
                                to: 'admin.tracker.tasks.index',
                            )->gate(TrackerPermission::ViewTasks),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES]),
                        label: 'navigation.categories',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-tag',
                        activePatterns: ['admin.tracker.categories.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_CATEGORIES_VIEW]),
                                label: 'navigation.view_categories',
                                to: 'admin.tracker.categories.index',
                            )->gate(TrackerPermission::ViewCategories),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS]),
                        label: 'navigation.holidays',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-palmtree',
                        activePatterns: ['admin.tracker.holidays.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS, TenantMenu::MENU_MAIN_SECTION_TRACKER_ITEM_HOLIDAYS_VIEW]),
                                label: 'navigation.view_holidays',
                                to: 'admin.tracker.holidays.index',
                            )->gate(TrackerPermission::ManageHolidays),
                        ]),
                    ),
                ])->each(fn (MenuItem $item) => $item->module(TenantModule::Tracker)),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }

    /**
     * Register the Tools section of the tenant Main Menu
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerToolsSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_TOOLS;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_TOOLS_LABEL,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TOOLS_LABEL]),
                        label: 'navigation.tools',
                        type: MenuItemType::LABEL,
                    ),
                    (new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_TOOLS_ITEM_FILES,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_TOOLS_ITEM_FILES]),
                        label: 'navigation.files',
                        type: MenuItemType::LINK,
                        to: 'admin.file-manager.index',
                        icon: 'i-lucide-folder-open',
                    ))->gate(FileManagerPermission::ViewFiles),
                ]),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }

    /**
     * Register the Admin section of the tenant Main Menu
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerAdminSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_ADMIN;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_ADMIN_LABEL,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_LABEL]),
                        label: 'navigation.admin',
                        type: MenuItemType::LABEL,
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS]),
                        label: 'navigation.users',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-users',
                        activePatterns: ['admin.users.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_VIEW,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_VIEW]),
                                label: 'navigation.view_users',
                                to: 'admin.users.index',
                            )->gate(CorePermission::ViewUsers),
                            // TODO: Implement Create User
                            //                            new MenuChildItem(
                            //                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_CREATE,
                            //                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_USERS_CREATE]),
                            //                                label: 'navigation.create_user',
                            //                                to: 'admin.users.create',
                            //                            )->gate(CorePermission::ManageUsers),
                        ]),
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS]),
                        label: 'navigation.site_settings',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-sliders-horizontal',
                        activePatterns: ['admin.site-settings.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_GENERAL,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_GENERAL]),
                                label: 'navigation.general_settings',
                                to: 'admin.site-settings.general.edit',
                            )->gate(SettingsPermission::ViewSettings),
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_THEME,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_THEME]),
                                label: 'navigation.theme_settings',
                                to: 'admin.site-settings.theme.edit',
                            )->gate(SettingsPermission::ViewSettings),
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_MAIL,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_MAIL]),
                                label: 'navigation.mail_settings',
                                to: 'admin.site-settings.mail.edit',
                            )->gate(SettingsPermission::ViewSettings),
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_INTEGRATIONS,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_INTEGRATIONS]),
                                label: 'navigation.integration_settings',
                                to: 'admin.site-settings.integrations.edit',
                            )->gate(SettingsPermission::ViewSettings),
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_EMAIL_PREVIEWS,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_EMAIL_PREVIEWS]),
                                label: 'navigation.email_previews',
                                to: 'admin.site-settings.email-previews.index',
                            )->gate(SettingsPermission::ViewSettings),
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_API_TOKENS,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ADMIN_ITEM_SITE_SETTINGS_API_TOKENS]),
                                label: 'navigation.api_tokens',
                                to: 'admin.site-settings.api-tokens.index',
                            )->gate(SettingsPermission::ManageApiTokens),
                        ]),
                    ),
                ]),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }

    /**
     * Register the Settings section of the tenant Main Menu
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    protected function registerAccountSection(NavigationRegistry $registry): void
    {
        $sectionKey = TenantMenu::MENU_MAIN_SECTION_ACCOUNT;

        $registry->addSectionToMenu(
            new MenuSection(
                menuItems: Collection::wrap([
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_ACCOUNT_LABEL,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_LABEL]),
                        label: 'navigation.account',
                        type: MenuItemType::LABEL,
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS]),
                        label: 'navigation.settings',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-settings',
                        activePatterns: ['admin.settings.*'],
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_PROFILE,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_PROFILE]),
                                label: 'navigation.profile',
                                to: 'admin.settings.profile.edit',
                            ),
                            new MenuChildItem(
                                key: TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_SECURITY,
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS_SECURITY]),
                                label: 'navigation.security',
                                to: 'admin.settings.security.edit',
                            ),
                        ]),
                    ),
                ]),
                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey])
            ),
            $sectionKey,
            $this->menuKey
        );
    }
}

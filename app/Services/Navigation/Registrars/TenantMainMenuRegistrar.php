<?php

namespace App\Services\Navigation\Registrars;

use App\Enums\TenantModule;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\CrmPermission;
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
        $this->registerCrmSection($registry);
        $this->registerAdminSection($registry);
        $this->registerTrackerSection($registry);
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
                        key: TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS,
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
                ]),
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

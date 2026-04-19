<?php

namespace App\Services\Navigation\Registrars;

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
                        label: 'Dashboard',
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
                        key: 'account-label',
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, 'account-label']),
                        label: 'Account',
                        type: MenuItemType::LABEL,
                    ),
                    new MenuItem(
                        key: TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS,
                        sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS]),
                        label: 'Settings',
                        type: MenuItemType::LINK,
                        icon: 'i-lucide-settings',
                        children: Collection::wrap([
                            new MenuChildItem(
                                key: 'profile',
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS, 'profile']),
                                label: 'Profile',
                                to: 'admin.settings.profile.edit',
                            ),
                            new MenuChildItem(
                                key: 'security',
                                sortOrder: NavigationConfig::getSortOrderForKeyParts([$this->menuKey, $sectionKey, TenantMenu::MENU_MAIN_SECTION_ACCOUNT_ITEM_SETTINGS, 'security']),
                                label: 'Security',
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

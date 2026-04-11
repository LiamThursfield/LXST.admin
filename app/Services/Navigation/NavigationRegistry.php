<?php

namespace App\Services\Navigation;

use App\Services\Navigation\Collections\Menu;
use App\Services\Navigation\Collections\MenuSection;
use App\Services\Navigation\Data\MenuChildItem;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Exceptions\DuplicateMenuItemKeyException;
use App\Services\Navigation\Exceptions\DuplicateMenuKeyException;
use App\Services\Navigation\Exceptions\DuplicateMenuSectionKeyException;
use App\Services\Navigation\Exceptions\MenuKeyDoesNotExistException;
use App\Services\Navigation\Exceptions\MenuSectionKeyDoesNotExistException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class NavigationRegistry
{
    /**
     * A mapped collection of Menus, where each menu has a unique key which
     * allows for easy retrieval and updates of different menus and their sections/items
     *
     * @var Collection<string, Menu>
     */
    protected Collection $menus;

    public function __construct()
    {
        $this->menus = collect();
    }

    /**
     * Adds a Menu with the given key to the menus collection
     *
     * @return $this
     *
     * @throws DuplicateMenuKeyException
     */
    public function addMenu(Menu $menu, string $key): self
    {
        // Do not allow duplicate menus to be added
        if ($this->menus->has($key)) {
            throw new DuplicateMenuKeyException($key);
        }

        $this->menus->put($key, $menu);

        return $this;
    }

    /**
     * Adds a MenuSection with the given key to the Menu with the given menu key
     *
     * @return $this
     *
     * @throws DuplicateMenuSectionKeyException
     * @throws MenuKeyDoesNotExistException
     */
    public function addSectionToMenu(MenuSection $section, string $sectionKey, string $menuKey): self
    {
        $this->getMenuOrFail($menuKey)
            ->addMenuSection($section, $sectionKey);

        return $this;
    }

    /**
     * Adds a MenuItem to the given Menu->MenuSection (via their keys)
     *
     * @throws DuplicateMenuItemKeyException
     * @throws MenuSectionKeyDoesNotExistException
     * @throws MenuKeyDoesNotExistException
     */
    public function addMenuItemToSection(MenuItem $menuItem, string $sectionKey, string $menuKey): self
    {
        $this->getMenuOrFail($menuKey)
            ->addMenuItemToSection($menuItem, $sectionKey);

        return $this;
    }

    /**
     * Adds a MenuChildItem to the given Menu->MenuSection->MenuItem
     *
     * @return $this
     *
     * @throws Exceptions\MenuItemKeyDoesNotExistException
     * @throws MenuKeyDoesNotExistException
     * @throws MenuSectionKeyDoesNotExistException
     */
    public function addChildToMenuItem(MenuChildItem $childItem, string $menuItemKey, string $menuSectionKey, string $menuKey): self
    {
        $this->getMenuOrFail($menuKey)
            ->addChildToMenuItem($childItem, $menuItemKey, $menuSectionKey);

        return $this;
    }

    /**
     * Returns the Menu Item collection for the given Menu, if it exists.
     * Sorted by sort order, and ensuring there are no sections/items visible
     * that shouldn't be.
     *
     * @return Collection<int, Collection<int, MenuItem>>
     *
     * @throws MenuKeyDoesNotExistException
     */
    public function resolveForUser(string $menuKey, ?Authenticatable $user): Collection
    {
        return $this->getMenuOrFail($menuKey)->resolveForUser($user);
    }

    /**
     * Returns the Menu with the given key, if it exists
     *
     * @throws MenuKeyDoesNotExistException
     */
    protected function getMenuOrFail(string $key): Menu
    {
        $menu = $this->menus->get($key);
        if ($menu === null) {
            throw new MenuKeyDoesNotExistException($key);
        }

        return $menu;
    }
}

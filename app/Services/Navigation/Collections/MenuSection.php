<?php

namespace App\Services\Navigation\Collections;

use App\Services\Navigation\Data\MenuChildItem;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Exceptions\DuplicateMenuItemKeyException;
use App\Services\Navigation\Exceptions\MenuItemKeyDoesNotExistException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * A MenuSection contains one or more MenuItems, each keyed using the MenuItem->key
 */
class MenuSection
{
    /** @var Collection<string, MenuItem> */
    protected Collection $menuItems;

    /**
     * @param  Collection<int, MenuItem>|null  $menuItems
     */
    public function __construct(
        ?Collection $menuItems = null,
        public readonly int $sortOrder = 0,
    ) {
        if ($menuItems === null) {
            $this->menuItems = collect();
        } else {
            $this->menuItems = $menuItems->keyBy(fn (MenuItem $item) => $item->key);
        }
    }

    /**
     * Add a single MenuItem to the MenuSection
     *
     *
     * @return $this
     *
     * @throws DuplicateMenuItemKeyException
     */
    public function addMenuItem(MenuItem $menuItem, bool $allowReplace = false): self
    {
        // Ensure the menu item doesn't already exist if we are not allowing a replace
        if (! $allowReplace && $this->menuItems->has($menuItem->key)) {
            throw new DuplicateMenuItemKeyException($menuItem);
        }

        $this->menuItems->put($menuItem->key, $menuItem);

        return $this;
    }

    /**
     * Add a collection of MenuItems to the MenuSection
     *
     * @param  Collection<int, MenuItem>  $menuItems
     * @return $this
     *
     * @throws DuplicateMenuItemKeyException
     */
    public function addMenuItems(Collection $menuItems, bool $allowReplace = false): self
    {
        foreach ($menuItems as $menuItem) {
            $this->addMenuItem($menuItem, $allowReplace);
        }

        return $this;
    }

    /**
     * Adds a MenuChildItem to MenuItem with the given key, if it exists
     *
     * @throws MenuItemKeyDoesNotExistException
     */
    public function addChildToMenuItem(MenuChildItem $childItem, string $menuItemKey): self
    {
        $menuItem = $this->menuItems->get($menuItemKey);

        if ($menuItem === null) {
            throw new MenuItemKeyDoesNotExistException($menuItemKey);
        }

        $menuItem->addChild($childItem);

        return $this;
    }

    /**
     * Returns the MenuItem collection, filtering out any MenuItems/MenuChildItems that are not visible for the given user.
     * All items sorted by the item sortOrder.
     *
     * @return Collection<int, MenuItem>
     */
    public function resolveForUser(?Authenticatable $user): Collection
    {
        if ($this->menuItems->isEmpty()) {
            return new Collection;
        }

        // Ensure we hide any MenuItems or MenuChildItems that are not visible to the user
        /** @var Collection<int|string, MenuItem> $visible */
        $visible = $this->menuItems->mapWithKeys(function (MenuItem $menuItem) use ($user) {
            if (! $menuItem->isVisible($user)) {
                return [$menuItem->key => null];
            }

            return [$menuItem->key => $menuItem->resolveForUser($user)];
        })->filter(function (?MenuItem $menuItem) {
            if ($menuItem == null) {
                return false;
            }

            // We should never filter out visible labels or items with routes
            if ($menuItem->isLabel() || $menuItem->hasLink()) {
                return true;
            }

            // Otherwise, we should filter out any MenuItems that don't have children,
            // as this implies none of the children were visible to the current user,
            // and it isn't a 'navigable' link itself
            return $menuItem->children->isNotEmpty();
        });

        return $visible->sortBy('sortOrder')->values();
    }
}

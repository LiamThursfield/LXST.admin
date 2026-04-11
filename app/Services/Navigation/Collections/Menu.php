<?php

namespace App\Services\Navigation\Collections;

use App\Services\Navigation\Data\MenuChildItem;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Exceptions\DuplicateMenuItemKeyException;
use App\Services\Navigation\Exceptions\DuplicateMenuSectionKeyException;
use App\Services\Navigation\Exceptions\MenuItemKeyDoesNotExistException;
use App\Services\Navigation\Exceptions\MenuSectionKeyDoesNotExistException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * A Menu contains one or more MenuSections, each keyed to allow for easy management
 */
class Menu
{
    /** @var Collection<string, MenuSection> */
    protected Collection $menuSections;

    public function __construct()
    {
        $this->menuSections = collect();
    }

    /**
     * Add a MenuSection with the given key to the menu
     * Will throw an error if there is an existing menu section with the same key and allowReplace is false
     *
     * @throws DuplicateMenuSectionKeyException
     */
    public function addMenuSection(MenuSection $menuSection, string $key, bool $allowReplace = false): self
    {
        // Ensure the menu section doesn't already exist if we are not allowing a replacement
        if (! $allowReplace && $this->menuSections->has($key)) {
            throw new DuplicateMenuSectionKeyException($key);
        }

        $this->menuSections->put($key, $menuSection);

        return $this;
    }

    /**
     * Add a MenuItem to the MenuSection that has the given key
     * Will throw an error if the MenuSection does not exist,
     * or if there is already a MenuItem with the same key and allowReplace is false
     *
     *
     * @return $this
     *
     * @throws MenuSectionKeyDoesNotExistException
     * @throws DuplicateMenuItemKeyException
     */
    public function addMenuItemToSection(MenuItem $menuItem, string $menuSectionKey, bool $allowReplace = false): self
    {
        $this->findMenuSectionOrFail($menuSectionKey)
            ->addMenuItem($menuItem, $allowReplace);

        return $this;
    }

    /**
     * Add a MenuChildItem to the given MenuSection->MenuItem (via their keys)
     *
     * @return $this
     *
     * @throws MenuSectionKeyDoesNotExistException
     * @throws MenuItemKeyDoesNotExistException
     */
    public function addChildToMenuItem(MenuChildItem $childItem, string $menuItemKey, string $menuSectionKey): self
    {
        $this->findMenuSectionOrFail($menuSectionKey)
            ->addChildToMenuItem($childItem, $menuItemKey);

        return $this;
    }

    /**
     * Returns the MenuSection collection, ensuring each MenuSection is resolved for the given user.
     * Ensuring there are no items visible that shouldn't be.
     *
     * @return Collection<int, Collection<int, MenuItem>>
     */
    public function resolveForUser(?Authenticatable $user): Collection
    {
        if ($this->menuSections->isEmpty()) {
            return collect();
        }

        return $this->menuSections->sortBy('sortOrder')
            ->map(function (MenuSection $menuSection) use ($user) {
                return $menuSection->resolveForUser($user);
            })
            // remove any empty sections
            ->filter()
            ->values();
    }

    /**
     * Returns the MenuSection with the given key, if it exists
     *
     * @throws MenuSectionKeyDoesNotExistException
     */
    protected function findMenuSectionOrFail(string $key): MenuSection
    {
        $menuSection = $this->menuSections->get($key);
        if ($menuSection === null) {
            throw new MenuSectionKeyDoesNotExistException($key);
        }

        return $menuSection;
    }
}

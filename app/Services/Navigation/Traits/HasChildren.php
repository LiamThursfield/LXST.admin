<?php

namespace App\Services\Navigation\Traits;

use App\Services\Navigation\Data\MenuChildItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * @property Collection<int, MenuChildItem> $children
 */
trait HasChildren
{
    /**
     * Add a MenuItem to the children collection
     */
    public function addChild(MenuChildItem $child): self
    {
        $this->children->push($child);

        return $this;
    }

    /**
     * Returns a new instance of the current object, except the children are limited to those
     * that are visible
     *
     * Children, if present, are returned sorted by sortOrder
     */
    public function resolveForUser(?Authenticatable $user): static
    {
        $children = $this->children->map(function (MenuChildItem $child) use ($user) {
            if (! $child->isVisible($user)) {
                return null;
            }

            if ($child->children->isEmpty()) {
                return clone $child;
            }

            return $child->resolveForUser($user);
        })->filter();

        $group = clone $this;
        $group->children = $children->sortBy('sortOrder')->values();

        return $group;
    }
}

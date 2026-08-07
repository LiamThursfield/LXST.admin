<?php

namespace App\Policies\Tracker;

use App\Models\Tracker\Category;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;

class CategoryPolicy
{
    /**
     * Whether the user can list categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(TrackerPermission::ViewCategories);
    }

    /**
     * Whether the user can view the given category: it must be visible to them
     * (public, or their own) and they must hold the view permission.
     */
    public function view(User $user, Category $category): bool
    {
        if (! $user->can(TrackerPermission::ViewCategories)) {
            return false;
        }

        return $category->is_public || $category->user_id === $user->id;
    }

    /**
     * Whether the user can create categories. Creating a *public* category
     * additionally requires the ManagePublicCategories permission, which is
     * enforced in the store request based on the submitted is_public flag.
     */
    public function create(User $user): bool
    {
        return $user->can(TrackerPermission::ManageCategories);
    }

    /**
     * Whether the user can update the given category. Public categories are
     * managed by public-category managers; private categories only by their
     * owner.
     */
    public function update(User $user, Category $category): bool
    {
        if ($category->is_public) {
            return $user->can(TrackerPermission::ManagePublicCategories);
        }

        return $category->user_id === $user->id
            && $user->can(TrackerPermission::ManageCategories);
    }

    /**
     * Whether the user can delete the given category. Same rules as update.
     */
    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }
}

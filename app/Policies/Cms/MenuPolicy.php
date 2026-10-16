<?php

namespace App\Policies\Cms;

use App\Models\Cms\Menu;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;

/**
 * Menus shape the site, so they need the CMS structure permissions rather than content ones
 */
class MenuPolicy
{
    /**
     * Whether the user can list menus.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can view the given menu.
     */
    public function view(User $user, Menu $menu): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can create menus.
     */
    public function create(User $user): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can update the given menu.
     */
    public function update(User $user, Menu $menu): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can delete the given menu.
     * Menus used by content cannot be deleted; the controller reports that case.
     */
    public function delete(User $user, Menu $menu): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }
}

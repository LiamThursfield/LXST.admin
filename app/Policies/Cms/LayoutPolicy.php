<?php

namespace App\Policies\Cms;

use App\Models\Cms\Layout;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;

/**
 * Layouts shape the site, so they need the CMS structure permissions rather than content ones
 */
class LayoutPolicy
{
    /**
     * Whether the user can list layouts.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can view the given layout.
     */
    public function view(User $user, Layout $layout): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can create layouts.
     */
    public function create(User $user): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can update the given layout.
     */
    public function update(User $user, Layout $layout): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can delete the given layout.
     * Pages will use layouts (#51); deleting one in use will be blocked then.
     */
    public function delete(User $user, Layout $layout): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }
}

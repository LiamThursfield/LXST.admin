<?php

namespace App\Policies\Cms;

use App\Models\Cms\Redirect;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;

/**
 * Redirects shape the site, so they need the CMS structure permissions rather than content ones
 */
class RedirectPolicy
{
    /**
     * Whether the user can list redirects.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can view the given redirect.
     */
    public function view(User $user, Redirect $redirect): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can create redirects.
     */
    public function create(User $user): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can update the given redirect.
     */
    public function update(User $user, Redirect $redirect): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can delete the given redirect.
     */
    public function delete(User $user, Redirect $redirect): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }
}

<?php

namespace App\Policies\Cms;

use App\Models\Cms\SavedComponent;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;

/**
 * Saved components are content rather than structure, so an editor can update a price without being
 * able to change templates
 */
class SavedComponentPolicy
{
    /**
     * Whether the user can list saved components.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CmsPermission::ViewContent);
    }

    /**
     * Whether the user can view the given saved component.
     */
    public function view(User $user, SavedComponent $savedComponent): bool
    {
        return $user->can(CmsPermission::ViewContent);
    }

    /**
     * Whether the user can create saved components.
     */
    public function create(User $user): bool
    {
        return $user->can(CmsPermission::ManageContent);
    }

    /**
     * Whether the user can update the given saved component.
     */
    public function update(User $user, SavedComponent $savedComponent): bool
    {
        return $user->can(CmsPermission::ManageContent);
    }

    /**
     * Whether the user can delete the given saved component.
     */
    public function delete(User $user, SavedComponent $savedComponent): bool
    {
        return $user->can(CmsPermission::ManageContent);
    }
}

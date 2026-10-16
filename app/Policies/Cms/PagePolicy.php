<?php

namespace App\Policies\Cms;

use App\Models\Cms\Page;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;

/**
 * Pages are content, so they need the CMS content permissions (not the structure ones)
 */
class PagePolicy
{
    /**
     * Whether the user can list pages.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CmsPermission::ViewContent);
    }

    /**
     * Whether the user can view the given page.
     */
    public function view(User $user, Page $page): bool
    {
        return $user->can(CmsPermission::ViewContent);
    }

    /**
     * Whether the user can create pages.
     */
    public function create(User $user): bool
    {
        return $user->can(CmsPermission::ManageContent);
    }

    /**
     * Whether the user can update the given page.
     */
    public function update(User $user, Page $page): bool
    {
        return $user->can(CmsPermission::ManageContent);
    }

    /**
     * Whether the user can delete the given page.
     * Pages with children cannot be deleted unless forced; the controller reports that case.
     */
    public function delete(User $user, Page $page): bool
    {
        return $user->can(CmsPermission::ManageContent);
    }
}

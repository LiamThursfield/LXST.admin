<?php

namespace App\Policies\Cms;

use App\Models\Cms\Template;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;

/**
 * Templates shape the site, so they need the CMS structure permissions rather than content ones
 */
class TemplatePolicy
{
    /**
     * Whether the user can list templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can view the given template.
     */
    public function view(User $user, Template $template): bool
    {
        return $user->can(CmsPermission::ViewStructure);
    }

    /**
     * Whether the user can create templates.
     */
    public function create(User $user): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can update the given template.
     */
    public function update(User $user, Template $template): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }

    /**
     * Whether the user can delete the given template.
     * Templates that are in use cannot be deleted; the controller reports that case.
     */
    public function delete(User $user, Template $template): bool
    {
        return $user->can(CmsPermission::ManageStructure);
    }
}

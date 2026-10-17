<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\Role;

class CmsAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(CmsPermission::cases());

        // Assign the permissions to the roles

        // Admin can manage everything, including the site's structure
        $registry->assignPermissionsToRole(Role::Admin, CmsPermission::cases());

        // Editor manages content, and can see (but not change) the structure
        $registry->assignPermissionsToRole(Role::Editor, [
            CmsPermission::ViewContent,
            CmsPermission::ManageContent,
            CmsPermission::ViewStructure,
            CmsPermission::ViewAnalytics,
        ]);

        // Viewer can only view
        $registry->assignPermissionsToRole(Role::Viewer, [
            CmsPermission::ViewContent,
            CmsPermission::ViewStructure,
            CmsPermission::ViewAnalytics,
        ]);
    }
}

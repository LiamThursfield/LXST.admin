<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\Role;

class CoreAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(CorePermission::cases());

        // Assign the permissions to the roles

        // SuperAdmin: no permissions assigned here —
        // handled by Gate::before() bypass in PermissionServiceProvider.

        // Admin gets everything
        $registry->assignPermissionsToRole(Role::Admin, CorePermission::cases());

        // Editor can view but not manage
        $registry->assignPermissionsToRole(Role::Editor, [
            CorePermission::ViewPermissions,
            CorePermission::ViewUsers,
        ]);

        // Viewer has limited visibility
        $registry->assignPermissionsToRole(Role::Viewer, []);
    }
}

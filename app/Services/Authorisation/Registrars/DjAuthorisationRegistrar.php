<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Authorisation\Enums\Role;

class DjAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(DjPermission::cases());

        // Assign the permissions to the roles

        // Admin and Editor can manage everything, including the request queue
        $registry->assignPermissionsToRole(Role::Admin, DjPermission::cases());
        $registry->assignPermissionsToRole(Role::Editor, DjPermission::cases());

        // Viewer can only view
        $registry->assignPermissionsToRole(Role::Viewer, [
            DjPermission::ViewLibrary,
            DjPermission::ViewGigs,
            DjPermission::ViewStats,
        ]);
    }
}

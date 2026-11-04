<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Authorisation\Enums\Role;

class BookingsAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(BookingsPermission::cases());

        // Assign the permissions to the roles

        // Admin and Editor can view and manage services
        $registry->assignPermissionsToRole(Role::Admin, BookingsPermission::cases());
        $registry->assignPermissionsToRole(Role::Editor, BookingsPermission::cases());

        // Viewer can only view
        $registry->assignPermissionsToRole(Role::Viewer, [
            BookingsPermission::ViewServices,
        ]);
    }
}

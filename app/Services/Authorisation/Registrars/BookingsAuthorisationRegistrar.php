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

        // Admin and Editor can view and manage everything
        $registry->assignPermissionsToRole(Role::Admin, BookingsPermission::cases());
        $registry->assignPermissionsToRole(Role::Editor, BookingsPermission::cases());

        // Viewer can view services and the rota, and ask for their own time off. Other people's
        // time off can say why they're away, so it isn't included.
        $registry->assignPermissionsToRole(Role::Viewer, [
            BookingsPermission::ViewServices,
            BookingsPermission::ViewResources,
            BookingsPermission::RequestTimeOff,
        ]);
    }
}

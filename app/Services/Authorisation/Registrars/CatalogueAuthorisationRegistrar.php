<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\CataloguePermission;
use App\Services\Authorisation\Enums\Role;

class CatalogueAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(CataloguePermission::cases());

        // Assign the permissions to the roles

        // Admin and Editor can view and manage everything
        $registry->assignPermissionsToRole(Role::Admin, CataloguePermission::cases());
        $registry->assignPermissionsToRole(Role::Editor, CataloguePermission::cases());

        // Viewer can see catalogues
        $registry->assignPermissionsToRole(Role::Viewer, [
            CataloguePermission::ViewCatalogues,
        ]);
    }
}

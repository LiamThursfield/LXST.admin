<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\FileManagerPermission;
use App\Services\Authorisation\Enums\Role;

class FileManagerAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(FileManagerPermission::cases());

        // Assign the permissions to the roles

        // Admin and Editor can browse, upload and organise files
        $registry->assignPermissionsToRole(Role::Admin, FileManagerPermission::cases());
        $registry->assignPermissionsToRole(Role::Editor, FileManagerPermission::cases());

        // Viewer can only browse
        $registry->assignPermissionsToRole(Role::Viewer, [
            FileManagerPermission::ViewFiles,
        ]);
    }
}

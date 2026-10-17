<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\Role;
use App\Services\Authorisation\Enums\SettingsPermission;

class SettingsAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(SettingsPermission::cases());

        // Assign the permissions to the roles

        // Admin can view and change the site's settings, and manage its API tokens
        $registry->assignPermissionsToRole(Role::Admin, SettingsPermission::cases());

        // Editor can see them (e.g. to check the site name), but not change them
        $registry->assignPermissionsToRole(Role::Editor, [
            SettingsPermission::ViewSettings,
        ]);
    }
}

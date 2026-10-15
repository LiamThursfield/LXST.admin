<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\Role;
use App\Services\Authorisation\Enums\TrackerPermission;

class TrackerAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(TrackerPermission::cases());

        // Assign the permissions to the roles

        // Admin gets everything, including managing public categories
        $registry->assignPermissionsToRole(Role::Admin, TrackerPermission::cases());

        // Editor and Viewer get full control of their own tracker data,
        // but cannot publish shared/public categories
        $personalPermissions = TrackerPermission::personalCases();

        $registry->assignPermissionsToRole(Role::Editor, $personalPermissions);
        $registry->assignPermissionsToRole(Role::Viewer, $personalPermissions);
    }
}

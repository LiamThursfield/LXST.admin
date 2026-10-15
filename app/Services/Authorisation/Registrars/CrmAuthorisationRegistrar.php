<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Authorisation\Enums\Role;

class CrmAuthorisationRegistrar implements AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void
    {
        // Register the permissions
        $registry->addPermissions(CrmPermission::cases());

        // Assign the permissions to the roles

        // Admin and Editor can view and manage the CRM
        $registry->assignPermissionsToRole(Role::Admin, CrmPermission::cases());
        $registry->assignPermissionsToRole(Role::Editor, CrmPermission::cases());

        // Viewer can only view
        $registry->assignPermissionsToRole(Role::Viewer, [
            CrmPermission::ViewContacts,
            CrmPermission::ViewForms,
            CrmPermission::ViewOrganisationUnits,
        ]);
    }
}

<?php

namespace App\Policies\Crm;

use App\Models\Crm\OrganisationUnit;
use App\Models\User;
use App\Services\Authorisation\Enums\CrmPermission;

class OrganisationUnitPolicy
{
    /**
     * Whether the user can list organisation units.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CrmPermission::ViewOrganisationUnits);
    }

    /**
     * Whether the user can view the given organisation unit.
     */
    public function view(User $user, OrganisationUnit $organisationUnit): bool
    {
        return $user->can(CrmPermission::ViewOrganisationUnits);
    }

    /**
     * Whether the user can create organisation units.
     */
    public function create(User $user): bool
    {
        return $user->can(CrmPermission::ManageOrganisationUnits);
    }

    /**
     * Whether the user can update the given organisation unit.
     */
    public function update(User $user, OrganisationUnit $organisationUnit): bool
    {
        return $user->can(CrmPermission::ManageOrganisationUnits);
    }

    /**
     * Whether the user can delete the given organisation unit.
     * Units that still have children cannot be deleted; the controller reports that case.
     */
    public function delete(User $user, OrganisationUnit $organisationUnit): bool
    {
        return $user->can(CrmPermission::ManageOrganisationUnits);
    }
}

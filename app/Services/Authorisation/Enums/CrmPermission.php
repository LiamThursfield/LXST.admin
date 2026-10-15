<?php

namespace App\Services\Authorisation\Enums;

enum CrmPermission: string
{
    case ViewOrganisationUnits = 'crm.view-organisation-units';
    case ManageOrganisationUnits = 'crm.manage-organisation-units';
}

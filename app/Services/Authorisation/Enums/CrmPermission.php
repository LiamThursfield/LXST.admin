<?php

namespace App\Services\Authorisation\Enums;

enum CrmPermission: string
{
    case ViewContacts = 'crm.view-contacts';
    case ManageContacts = 'crm.manage-contacts';

    case ViewForms = 'crm.view-forms';
    case ManageForms = 'crm.manage-forms';

    case ViewOrganisationUnits = 'crm.view-organisation-units';
    case ManageOrganisationUnits = 'crm.manage-organisation-units';
}

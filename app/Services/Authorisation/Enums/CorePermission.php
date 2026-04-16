<?php

namespace App\Services\Authorisation\Enums;

enum CorePermission: string
{
    case ViewPermissions = 'core.view-permissions';
    case ManagePermissions = 'core.manage-permissions';

    case ViewUsers = 'core.view-users';
    case ManageUsers = 'core.manage-users';
}

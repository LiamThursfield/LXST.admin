<?php

namespace App\Services\Authorisation\Enums;

enum FileManagerPermission: string
{
    case ViewFiles = 'file-manager.view-files';
    case ManageFiles = 'file-manager.manage-files';
}

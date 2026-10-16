<?php

namespace App\Services\Authorisation\Enums;

enum SettingsPermission: string
{
    case ViewSettings = 'settings.view-settings';
    case ManageSettings = 'settings.manage-settings';
}

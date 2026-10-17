<?php

namespace App\Services\Authorisation\Enums;

enum SettingsPermission: string
{
    case ViewSettings = 'settings.view-settings';
    case ManageSettings = 'settings.manage-settings';

    /** API tokens grant write access to the tenant API, so they're kept apart from other settings */
    case ManageApiTokens = 'settings.manage-api-tokens';
}

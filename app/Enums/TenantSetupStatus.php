<?php

namespace App\Enums;

use App\Enums\Traits\HasLabels;

/**
 * Where setting up a tenant's database (and first admin) has got to. Only a ready tenant's site,
 * admin and API answer.
 */
enum TenantSetupStatus: string
{
    use HasLabels;

    case SettingUp = 'setting_up';
    case Ready = 'ready';
    case Failed = 'failed';
}

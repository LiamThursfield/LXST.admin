<?php

namespace App\Enums;

use App\Enums\Traits\HasLabels;

/**
 * The optional modules a tenant can have switched on
 */
enum TenantModule: string
{
    use HasLabels;

    case Cms = 'cms';
    case Crm = 'crm';
    case Dj = 'dj';
    case Tracker = 'tracker';
}

<?php

namespace App\Enums\Cms;

use App\Enums\Traits\HasLabels;

/**
 * Whether a redirect currently applies, worked out from its enabled flag and publish/expiry dates
 */
enum RedirectStatus: string
{
    use HasLabels;

    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case Disabled = 'disabled';
}

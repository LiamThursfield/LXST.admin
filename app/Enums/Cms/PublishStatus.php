<?php

namespace App\Enums\Cms;

use App\Enums\Traits\HasLabels;

/**
 * Whether a URL (e.g. a page's) is live, worked out from its enabled flag and publish/expiry dates
 */
enum PublishStatus: string
{
    use HasLabels;

    case Published = 'published';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case Draft = 'draft';
}

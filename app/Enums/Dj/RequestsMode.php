<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * Whether a gig takes song requests
 */
enum RequestsMode: string
{
    use HasLabels;

    case Open = 'open';
    case Closed = 'closed';

    /** Open from a set time before the gig starts until it ends */
    case Scheduled = 'scheduled';
}

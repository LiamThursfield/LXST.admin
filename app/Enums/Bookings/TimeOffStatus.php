<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Only approved time off makes a resource unavailable
 */
enum TimeOffStatus: string
{
    use HasLabels;

    case Requested = 'requested';
    case Approved = 'approved';
    case Declined = 'declined';
}

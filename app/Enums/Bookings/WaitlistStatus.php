<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where someone on the waitlist has got to
 */
enum WaitlistStatus: string
{
    use HasLabels;

    case Waiting = 'waiting';
    case Booked = 'booked';
    case Removed = 'removed';
}

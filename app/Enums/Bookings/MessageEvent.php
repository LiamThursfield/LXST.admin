<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * What a booking message is about. Reminders and follow-ups come later (#179).
 */
enum MessageEvent: string
{
    use HasLabels;

    case Booked = 'booked';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
}

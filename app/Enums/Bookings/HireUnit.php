<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * What a hired service is priced and booked by
 */
enum HireUnit: string
{
    use HasLabels;

    case Hour = 'hour';
    case Day = 'day';
}

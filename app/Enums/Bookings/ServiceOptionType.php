<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * A customer chooses one of a service's variations, and any of its add-ons
 */
enum ServiceOptionType: string
{
    use HasLabels;

    case Variation = 'variation';
    case AddOn = 'add_on';
}

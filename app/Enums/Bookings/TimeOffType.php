<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Why a resource isn't available. Maintenance is for spaces, equipment and vehicles.
 */
enum TimeOffType: string
{
    use HasLabels;

    case Holiday = 'holiday';
    case Sickness = 'sickness';
    case Maintenance = 'maintenance';
    case Other = 'other';
}

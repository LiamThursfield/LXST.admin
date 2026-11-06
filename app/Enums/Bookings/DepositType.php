<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Whether a deposit is a set amount or a share of the price
 */
enum DepositType: string
{
    use HasLabels;

    case Fixed = 'fixed';
    case Percent = 'percent';
}

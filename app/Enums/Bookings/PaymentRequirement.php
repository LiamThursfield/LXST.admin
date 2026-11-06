<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * What a service asks to be paid ahead
 */
enum PaymentRequirement: string
{
    use HasLabels;

    case None = 'none';
    case Deposit = 'deposit';
    case Full = 'full';
}

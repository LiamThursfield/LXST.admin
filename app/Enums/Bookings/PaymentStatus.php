<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where a payment has got to
 */
enum PaymentStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
}

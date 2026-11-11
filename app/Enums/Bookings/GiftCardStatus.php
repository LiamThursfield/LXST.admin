<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where a gift card has got to: waiting to be paid for online, ready to use, all used, past its
 * date (or never paid for), or cancelled by staff
 */
enum GiftCardStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}

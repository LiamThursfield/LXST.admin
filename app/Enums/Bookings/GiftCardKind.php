<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * What a gift card is worth: an amount spent over any number of bookings, or one booking of a
 * service paid in full
 */
enum GiftCardKind: string
{
    use HasLabels;

    case Amount = 'amount';
    case Service = 'service';
}

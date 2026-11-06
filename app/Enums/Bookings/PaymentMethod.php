<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * How a payment was made: online through Stripe, or in person
 */
enum PaymentMethod: string
{
    use HasLabels;

    case Online = 'online';
    case Cash = 'cash';
    case Card = 'card';
    case Other = 'other';

    public function isInPerson(): bool
    {
        return $this !== self::Online;
    }
}

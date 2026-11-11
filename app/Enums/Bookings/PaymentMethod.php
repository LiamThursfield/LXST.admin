<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * How a payment was made: online through Stripe, in person, or from a gift card
 */
enum PaymentMethod: string
{
    use HasLabels;

    case Online = 'online';
    case Cash = 'cash';
    case Card = 'card';
    case Other = 'other';
    case GiftCard = 'gift_card';

    public function isInPerson(): bool
    {
        return in_array($this, [self::Cash, self::Card, self::Other], true);
    }
}

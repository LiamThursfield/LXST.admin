<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * How a service's price is shown
 */
enum ServicePriceType: string
{
    use HasLabels;

    case Fixed = 'fixed';
    case From = 'from';
    case OnQuote = 'on_quote';

    public function hasAmount(): bool
    {
        return $this !== self::OnQuote;
    }
}

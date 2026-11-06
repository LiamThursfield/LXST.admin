<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * What a payment is for: towards the booking's price, or a fee for not coming or cancelling late
 */
enum PaymentPurpose: string
{
    use HasLabels;

    case Booking = 'booking';
    case NoShowFee = 'no_show_fee';
    case LateCancellationFee = 'late_cancellation_fee';

    public function isFee(): bool
    {
        return $this !== self::Booking;
    }
}

<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * How a customer is told about their bookings
 */
enum MessageChannel: string
{
    use HasLabels;

    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
}

<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where a quote has got to: being written, sent to the customer, accepted or declined by them, or
 * sent and past its date without an answer. Expired is worked out, never stored.
 */
enum QuoteStatus: string
{
    use HasLabels;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
}

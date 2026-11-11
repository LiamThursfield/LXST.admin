<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where an invoice has got to: issued, sent to the customer, or voided. Paid and overdue are worked
 * out from what's been paid towards the booking and the due date, never stored.
 */
enum InvoiceStatus: string
{
    use HasLabels;

    case Issued = 'issued';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
}

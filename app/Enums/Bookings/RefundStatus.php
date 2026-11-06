<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where a refund has got to. Card refunds are usually done at once, but some wait on the bank.
 */
enum RefundStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * From Stripe's own status for a refund
     */
    public static function fromStripe(?string $status): self
    {
        return match ($status) {
            'succeeded' => self::Succeeded,
            'failed', 'canceled' => self::Failed,
            default => self::Pending,
        };
    }
}

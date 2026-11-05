<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where a booking has got to. The words work for a haircut, a table, a van hire or a class.
 */
enum BookingStatus: string
{
    use HasLabels;

    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Whether the booking still ties up its resources and places
     */
    public function holdsTime(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow], true);
    }

    /**
     * The statuses it can move to from here
     *
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::CheckedIn, self::Completed, self::NoShow, self::Cancelled],
            self::CheckedIn => [self::Completed, self::NoShow],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function holdingTime(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->holdsTime()));
    }
}

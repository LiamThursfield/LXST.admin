<?php

namespace App\Data\Bookings;

use App\Models\Bookings\BookableResource;
use Carbon\CarbonImmutable;

/**
 * A time a booking would fit, and what it would tie up
 */
readonly class BookingSlot
{
    /**
     * @param  list<BookableResource>  $resources
     */
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public CarbonImmutable $blockedFrom,
        public CarbonImmutable $blockedUntil,
        public array $resources,
    ) {}
}

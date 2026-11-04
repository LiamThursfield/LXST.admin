<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * What a resource is. Anything a booking can need: who does the work, and where or with what.
 */
enum ResourceType: string
{
    use HasLabels;

    case Person = 'person';
    case Space = 'space';
    case Equipment = 'equipment';
    case Vehicle = 'vehicle';

    public function isPerson(): bool
    {
        return $this === self::Person;
    }

    public function hasCapacity(): bool
    {
        return $this === self::Space;
    }
}

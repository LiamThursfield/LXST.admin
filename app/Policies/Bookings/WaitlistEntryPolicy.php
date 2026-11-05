<?php

namespace App\Policies\Bookings;

use App\Models\Bookings\WaitlistEntry;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;

class WaitlistEntryPolicy
{
    /**
     * Whether the user can see the waitlist.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(BookingsPermission::ViewBookings);
    }

    /**
     * Whether the user can add someone to the waitlist.
     */
    public function create(User $user): bool
    {
        return $user->can(BookingsPermission::ManageBookings);
    }

    /**
     * Whether the user can book or remove the given entry.
     */
    public function update(User $user, WaitlistEntry $entry): bool
    {
        return $user->can(BookingsPermission::ManageBookings);
    }
}

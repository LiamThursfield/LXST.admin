<?php

namespace App\Policies\Bookings;

use App\Models\Bookings\Booking;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;

class BookingPolicy
{
    /**
     * Whether the user can list bookings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(BookingsPermission::ViewBookings);
    }

    /**
     * Whether the user can view the given booking.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->can(BookingsPermission::ViewBookings);
    }

    /**
     * Whether the user can create bookings.
     */
    public function create(User $user): bool
    {
        return $user->can(BookingsPermission::ManageBookings);
    }

    /**
     * Whether the user can change the given booking's status or time.
     */
    public function update(User $user, Booking $booking): bool
    {
        return $user->can(BookingsPermission::ManageBookings);
    }
}

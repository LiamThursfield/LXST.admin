<?php

namespace App\Policies\Bookings;

use App\Models\Bookings\ServiceSession;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;

class ServiceSessionPolicy
{
    /**
     * Whether the user can see the timetable.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(BookingsPermission::ViewBookings);
    }

    /**
     * Whether the user can add to the timetable.
     */
    public function create(User $user): bool
    {
        return $user->can(BookingsPermission::ManageBookings);
    }

    /**
     * Whether the user can change the given timetable entry.
     */
    public function update(User $user, ServiceSession $session): bool
    {
        return $user->can(BookingsPermission::ManageBookings);
    }

    /**
     * Whether the user can delete the given timetable entry.
     */
    public function delete(User $user, ServiceSession $session): bool
    {
        return $user->can(BookingsPermission::ManageBookings);
    }
}

<?php

namespace App\Policies\Bookings;

use App\Models\Bookings\Service;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;

class ServicePolicy
{
    /**
     * Whether the user can list services.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(BookingsPermission::ViewServices);
    }

    /**
     * Whether the user can view the given service.
     */
    public function view(User $user, Service $service): bool
    {
        return $user->can(BookingsPermission::ViewServices);
    }

    /**
     * Whether the user can create services.
     */
    public function create(User $user): bool
    {
        return $user->can(BookingsPermission::ManageServices);
    }

    /**
     * Whether the user can update the given service.
     */
    public function update(User $user, Service $service): bool
    {
        return $user->can(BookingsPermission::ManageServices);
    }

    /**
     * Whether the user can delete the given service (a soft delete).
     */
    public function delete(User $user, Service $service): bool
    {
        return $user->can(BookingsPermission::ManageServices);
    }
}

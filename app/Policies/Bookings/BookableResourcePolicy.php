<?php

namespace App\Policies\Bookings;

use App\Models\Bookings\BookableResource;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;

class BookableResourcePolicy
{
    /**
     * Whether the user can list resources and see the rota.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(BookingsPermission::ViewResources);
    }

    /**
     * Whether the user can view the given resource.
     */
    public function view(User $user, BookableResource $resource): bool
    {
        return $user->can(BookingsPermission::ViewResources);
    }

    /**
     * Whether the user can create resources.
     */
    public function create(User $user): bool
    {
        return $user->can(BookingsPermission::ManageResources);
    }

    /**
     * Whether the user can update the given resource, including its shifts on the rota.
     */
    public function update(User $user, BookableResource $resource): bool
    {
        return $user->can(BookingsPermission::ManageResources);
    }

    /**
     * Whether the user can delete the given resource (a soft delete).
     */
    public function delete(User $user, BookableResource $resource): bool
    {
        return $user->can(BookingsPermission::ManageResources);
    }
}

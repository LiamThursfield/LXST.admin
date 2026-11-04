<?php

namespace App\Policies\Bookings;

use App\Models\Bookings\ServiceCategory;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;

class ServiceCategoryPolicy
{
    /**
     * Whether the user can list service categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(BookingsPermission::ViewServices);
    }

    /**
     * Whether the user can create service categories.
     */
    public function create(User $user): bool
    {
        return $user->can(BookingsPermission::ManageServices);
    }

    /**
     * Whether the user can update the given service category.
     */
    public function update(User $user, ServiceCategory $category): bool
    {
        return $user->can(BookingsPermission::ManageServices);
    }

    /**
     * Whether the user can delete the given service category. Its services become uncategorised.
     */
    public function delete(User $user, ServiceCategory $category): bool
    {
        return $user->can(BookingsPermission::ManageServices);
    }
}

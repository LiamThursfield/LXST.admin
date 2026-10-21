<?php

namespace App\Policies\Dj;

use App\Models\Dj\Gig;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

class GigPolicy
{
    /**
     * Whether the user can list gigs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(DjPermission::ViewGigs);
    }

    /**
     * Whether the user can view the given gig.
     */
    public function view(User $user, Gig $gig): bool
    {
        return $user->can(DjPermission::ViewGigs);
    }

    /**
     * Whether the user can create gigs.
     */
    public function create(User $user): bool
    {
        return $user->can(DjPermission::ManageGigs);
    }

    /**
     * Whether the user can update (or pin) the given gig.
     */
    public function update(User $user, Gig $gig): bool
    {
        return $user->can(DjPermission::ManageGigs);
    }

    /**
     * Whether the user can see the given gig's stats (#126).
     */
    public function viewStats(User $user, Gig $gig): bool
    {
        return $user->can(DjPermission::ViewStats);
    }

    /**
     * Whether the user can delete the given gig.
     */
    public function delete(User $user, Gig $gig): bool
    {
        return $user->can(DjPermission::ManageGigs);
    }
}

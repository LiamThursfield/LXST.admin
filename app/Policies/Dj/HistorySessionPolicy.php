<?php

namespace App\Policies\Dj;

use App\Models\Dj\HistorySession;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

/**
 * Play history is seen by those who can see gigs, and linked to gigs by those who manage them
 */
class HistorySessionPolicy
{
    /**
     * Whether the user can see play history.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(DjPermission::ViewGigs);
    }

    /**
     * Whether the user can view the given session.
     */
    public function view(User $user, HistorySession $session): bool
    {
        return $user->can(DjPermission::ViewGigs);
    }

    /**
     * Whether the user can link sessions to gigs, change their stats flag, or add plays by hand.
     */
    public function update(User $user, ?HistorySession $session = null): bool
    {
        return $user->can(DjPermission::ManageGigs);
    }
}

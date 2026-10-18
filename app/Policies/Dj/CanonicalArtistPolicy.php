<?php

namespace App\Policies\Dj;

use App\Models\Dj\CanonicalArtist;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

/**
 * Canonical records are reviewed with the library, and merged, split or verified by those who
 * manage it
 */
class CanonicalArtistPolicy
{
    /**
     * Whether the user can see canonical records and matching suggestions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(DjPermission::ViewLibrary);
    }

    /**
     * Whether the user can merge, split, verify or re-match canonical records.
     */
    public function update(User $user, ?CanonicalArtist $record = null): bool
    {
        return $user->can(DjPermission::ManageLibrary);
    }
}

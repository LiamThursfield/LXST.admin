<?php

namespace App\Policies\Dj;

use App\Models\Dj\SongList;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

/**
 * Setlists are seen by those who can see gigs, and built by those who manage setlists
 */
class SongListPolicy
{
    /**
     * Whether the user can list setlists.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(DjPermission::ViewGigs);
    }

    /**
     * Whether the user can view the given list.
     */
    public function view(User $user, SongList $list): bool
    {
        return $user->can(DjPermission::ViewGigs);
    }

    /**
     * Whether the user can create setlists.
     */
    public function create(User $user): bool
    {
        return $user->can(DjPermission::ManageSetlists);
    }

    /**
     * Whether the user can change the given list, including adding, reordering and removing songs.
     */
    public function update(User $user, SongList $list): bool
    {
        return $user->can(DjPermission::ManageSetlists);
    }

    /**
     * Whether the user can delete the given list.
     */
    public function delete(User $user, SongList $list): bool
    {
        return $user->can(DjPermission::ManageSetlists);
    }
}

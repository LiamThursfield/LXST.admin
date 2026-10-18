<?php

namespace App\Policies\Dj;

use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

/**
 * A setlist's passes are managed by those who manage setlists, and a gig's request passes by those
 * who manage requests
 */
class GuestPassPolicy
{
    /**
     * Whether the user can see and add passes on the given list.
     */
    public function create(User $user, SongList $list): bool
    {
        return $user->can($list->isSetlist() ? DjPermission::ManageSetlists : DjPermission::ManageRequests);
    }

    /**
     * Whether the user can change or revoke the given pass.
     */
    public function update(User $user, GuestPass $pass): bool
    {
        return $this->create($user, $pass->songList);
    }

    /**
     * Whether the user can delete the given pass.
     */
    public function delete(User $user, GuestPass $pass): bool
    {
        return $this->create($user, $pass->songList);
    }
}

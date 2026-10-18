<?php

namespace App\Policies\Dj;

use App\Enums\Dj\SongListKind;
use App\Models\Dj\SongListItem;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

/**
 * Song requests are handled by those who manage requests. Setlist songs are managed through
 * their setlist (see SongListPolicy).
 */
class SongListItemPolicy
{
    /**
     * Whether the user can see the request queue and open or close requests.
     */
    public function viewRequests(User $user): bool
    {
        return $user->can(DjPermission::ManageRequests);
    }

    /**
     * Whether the user can accept, decline or mark the given request played.
     */
    public function updateStatus(User $user, SongListItem $item): bool
    {
        return $item->songList->kind === SongListKind::Requests && $user->can(DjPermission::ManageRequests);
    }
}

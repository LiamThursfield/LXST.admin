<?php

namespace App\Policies\Dj;

use App\Models\Dj\LibraryArtist;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

/**
 * Library records come from imports, so they're only browsed here, never edited
 */
class LibraryArtistPolicy
{
    /**
     * Whether the user can browse the library.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(DjPermission::ViewLibrary);
    }

    /**
     * Whether the user can view the given record.
     */
    public function view(User $user, LibraryArtist $record): bool
    {
        return $user->can(DjPermission::ViewLibrary);
    }
}

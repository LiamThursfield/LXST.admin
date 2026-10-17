<?php

namespace App\Policies\Dj;

use App\Models\Dj\Library;
use App\Models\User;
use App\Services\Authorisation\Enums\DjPermission;

class LibraryPolicy
{
    /**
     * Whether the user can list libraries and their imports.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(DjPermission::ViewLibrary);
    }

    /**
     * Whether the user can view the given library.
     */
    public function view(User $user, Library $library): bool
    {
        return $user->can(DjPermission::ViewLibrary);
    }

    /**
     * Whether the user can create libraries.
     */
    public function create(User $user): bool
    {
        return $user->can(DjPermission::ManageLibrary);
    }

    /**
     * Whether the user can import into the given library.
     */
    public function import(User $user, Library $library): bool
    {
        return $user->can(DjPermission::ManageLibrary);
    }

    /**
     * Whether the user can delete the given library.
     */
    public function delete(User $user, Library $library): bool
    {
        return $user->can(DjPermission::ManageLibrary);
    }
}

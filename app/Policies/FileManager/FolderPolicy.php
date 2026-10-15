<?php

namespace App\Policies\FileManager;

use App\Models\User;
use App\Services\Authorisation\Enums\FileManagerPermission;

class FolderPolicy
{
    /**
     * Whether the user can browse the File Manager.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(FileManagerPermission::ViewFiles);
    }

    /**
     * Whether the user can create folders. Also requires uploads to be enabled (config/file-manager.php).
     */
    public function create(User $user): bool
    {
        return $user->can(FileManagerPermission::ManageFiles)
            && config('file-manager.uploads_enabled');
    }
}

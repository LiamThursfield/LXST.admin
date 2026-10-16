<?php

namespace App\Policies\FileManager;

use App\Models\FileManager\File;
use App\Models\User;
use App\Services\Authorisation\Enums\FileManagerPermission;

class FilePolicy
{
    /**
     * Whether the user can upload files. Also requires uploads to be enabled (config/file-manager.php).
     */
    public function create(User $user): bool
    {
        return $user->can(FileManagerPermission::ManageFiles)
            && config('file-manager.uploads_enabled');
    }

    /**
     * Whether the user can edit the given file's details, e.g. its alt text.
     */
    public function update(User $user, File $file): bool
    {
        return $user->can(FileManagerPermission::ManageFiles);
    }
}

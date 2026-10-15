<?php

namespace App\Services\FileManager;

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reads and writes the current tenant's File Manager disk, keeping the folder and file records in step
 */
class FileManagerStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('file-manager.disk'));
    }

    /**
     * The public URL of a file. Local files are served through tenancy's asset route,
     * since each tenant's files live in that tenant's own storage directory.
     */
    public function url(File $file): string
    {
        $disk = (string) config('file-manager.disk');

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return tenant_asset('file-manager/'.$file->path);
        }

        return $this->disk()->url($file->path);
    }

    /**
     * Creates a folder inside the given parent (or at the top level), on the disk and as a record
     */
    public function createFolder(string $name, ?Folder $parent = null): Folder
    {
        $path = $parent === null ? $name : $parent->path.'/'.$name;

        return DB::transaction(function () use ($name, $parent, $path) {
            $folder = Folder::create([
                'parent_id' => $parent?->id,
                'name' => $name,
                'path' => $path,
            ]);

            $this->disk()->makeDirectory($path);

            return $folder;
        });
    }
}

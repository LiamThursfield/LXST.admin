<?php

namespace App\Services\FileManager;

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Stores an uploaded file in the given folder (or at the top level) and records it.
     * The name is made URL-friendly, and a suffix added if the folder already has a file with that name.
     */
    public function storeUpload(UploadedFile $upload, ?Folder $folder = null): File
    {
        $name = $this->availableName($this->cleanName($upload), $folder);
        $path = $folder === null ? $name : $folder->path.'/'.$name;

        $this->disk()->putFileAs($folder->path ?? '', $upload, $name);

        return File::create([
            'folder_id' => $folder?->id,
            'name' => $name,
            'path' => $path,
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize() ?: 0,
        ]);
    }

    /**
     * The upload's name made URL-friendly, keeping its (lower case) extension, e.g. "Team Photo.JPG" → "team-photo.jpg"
     */
    protected function cleanName(UploadedFile $upload): string
    {
        $extension = Str::lower($upload->getClientOriginalExtension());
        $base = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';

        return $extension === '' ? $base : $base.'.'.$extension;
    }

    /**
     * The name, or the name with a numeric suffix ("photo-2.jpg"), that no file in the folder uses yet
     */
    protected function availableName(string $name, ?Folder $folder): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $candidate = $name;
        $suffix = 2;

        while ($this->nameTaken($candidate, $folder)) {
            $candidate = $base.'-'.$suffix++.($extension === '' ? '' : '.'.$extension);
        }

        return $candidate;
    }

    protected function nameTaken(string $name, ?Folder $folder): bool
    {
        $path = $folder === null ? $name : $folder->path.'/'.$name;

        return File::query()->where('path', $path)->exists() || $this->disk()->exists($path);
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

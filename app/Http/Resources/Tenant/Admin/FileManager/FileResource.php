<?php

namespace App\Http\Resources\Tenant\Admin\FileManager;

use App\Models\FileManager\File;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var File $file */
        $file = $this->resource;

        return [
            'id' => $file->id,
            'folder_id' => $file->folder_id,
            'name' => $file->name,
            'path' => $file->path,
            'url' => app(FileManagerStorage::class)->url($file),
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'alt_text' => $file->alt_text,
            'is_image' => $file->isImage(),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }
}

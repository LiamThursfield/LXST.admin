<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\LibraryImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LibraryImport $import */
        $import = $this->resource;

        return [
            'id' => $import->id,
            'library' => $import->library->name,
            'source_label' => $import->source->label(),
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'is_running' => $import->status->isRunning(),
            'progress' => $import->progress(),
            'original_filename' => $import->original_filename,
            'counts' => $import->counts,
            'error' => $import->error,
            'user' => $import->user?->name,
            'created_at' => $import->created_at->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
        ];
    }
}

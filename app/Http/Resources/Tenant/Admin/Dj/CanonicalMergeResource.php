<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalMerge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CanonicalMergeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CanonicalMerge $merge */
        $merge = $this->resource;

        return [
            'id' => $merge->id,
            'record' => $merge->record->value,
            'record_label' => $merge->record->label(),
            'action' => $merge->action->value,
            'action_label' => $merge->action->label(),
            'source_name' => $merge->source_name,
            'target_name' => $merge->target_name,
            'moved_count' => $merge->moved_count,
            // Null means the re-match did it
            'user' => $merge->user?->name,
            'created_at' => $merge->created_at->toIso8601String(),
        ];
    }
}

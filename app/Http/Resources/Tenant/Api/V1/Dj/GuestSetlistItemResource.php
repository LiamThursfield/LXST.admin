<?php

namespace App\Http\Resources\Tenant\Api\V1\Dj;

use App\Models\Dj\GuestPass;
use App\Models\Dj\SongListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A song on a setlist, as a guest sees it. Who added it is a guest's label and name, never a
 * user's.
 */
class GuestSetlistItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SongListItem $item */
        $item = $this->resource;
        /** @var GuestPass|null $pass */
        $pass = $request->attributes->get('guest_pass');

        return [
            'id' => $item->id,
            'title' => $item->title(),
            'artist' => $item->artist(),
            'is_custom' => $item->canonical_track_id === null,
            'type' => $item->type?->value,
            'type_label' => $item->type?->label(),
            'note' => $item->note,
            'added_by' => $item->guest_pass_id === null ? null : $item->addedByName(),
            // Guests can change and remove only the songs their pass added
            'is_mine' => $pass !== null && $item->guest_pass_id === $pass->id,
        ];
    }
}

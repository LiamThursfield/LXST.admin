<?php

namespace App\Http\Resources\Tenant\Api\V1\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A setlist, as a guest with a pass sees it
 */
class GuestSetlistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SongList $setlist */
        $setlist = $this->resource;
        /** @var GuestPass $pass */
        $pass = $request->attributes->get('guest_pass');
        $gig = $setlist->gig;

        return [
            'id' => $setlist->public_id,
            'name' => $setlist->name,
            'description' => $setlist->description,
            'gig' => $gig === null ? null : [
                'name' => $gig->name,
                'venue' => $gig->venue,
                'starts_at' => $gig->starts_at->toIso8601String(),
                'timezone' => $gig->timezone,
            ],
            'pass' => ['label' => $pass->label, 'can_add' => $pass->can_add],
            'types' => SetlistSongType::asSelectItems(),
            'items' => GuestSetlistItemResource::collection($setlist->items)->resolve($request),
        ];
    }
}

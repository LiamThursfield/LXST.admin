<?php

namespace App\Http\Resources\Tenant\Api\V1\Dj;

use App\Models\Dj\SongListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request on a gig's public list. The requester's name shows only if they opted in, and the
 * guest pass's label only if the gig's settings say so; messages never show.
 */
class PublicRequestResource extends JsonResource
{
    public function __construct(SongListItem $item, protected bool $showPassLabel = false)
    {
        parent::__construct($item);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SongListItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'title' => $item->title(),
            'artist' => $item->artist(),
            'is_custom' => $item->canonical_track_id === null,
            'status' => $item->status?->value,
            'votes' => (int) $item->getAttribute('votes_count'),
            'requester_name' => $item->show_requester_name ? $item->requester_name : null,
            'pass' => $this->showPassLabel ? $item->guestPass?->label : null,
            'played_at' => $item->played_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\SongListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A song on a setlist. Expects `song.artist` and `addedBy` loaded.
 */
class SetlistItemResource extends JsonResource
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
        $song = $item->song;

        return [
            'id' => $item->id,
            'title' => $item->title(),
            'artist' => $item->artist(),
            'is_custom' => $song === null,
            'song_url' => $song === null ? null : route('admin.dj.songs.show', $song),
            'bpm' => $song?->bpm === null ? null : (float) $song->bpm,
            'key' => LibraryTrackResource::key($song?->key),
            'duration' => $song?->formattedDuration(),
            'type' => $item->type?->value,
            'note' => $item->note,
            'added_by' => $item->addedByName(),
            'added_by_guest' => $item->guest_pass_id !== null,
            'created_at' => $item->created_at->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Models\Dj\SongListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A song request in the DJ's queue, with everything the requester sent
 */
class RequestQueueItemResource extends JsonResource
{
    /**
     * @param  list<SetlistSongType>  $setlistTypes  The request's types on the gig's setlists
     */
    public function __construct(SongListItem $item, protected array $setlistTypes = [])
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
        $song = $item->song;

        return [
            'id' => $item->id,
            'title' => $item->title(),
            'artist' => $item->artist(),
            'in_library' => $song !== null,
            'song_url' => $song === null ? null : route('admin.dj.songs.show', $song),
            'bpm' => $song?->bpm === null ? null : (float) $song->bpm,
            'key' => LibraryTrackResource::key($song?->key),
            'status' => $item->status?->value,
            'votes' => (int) $item->getAttribute('votes_count'),
            'requester_name' => $item->requester_name,
            'message' => $item->message,
            'pass' => $item->guestPass === null ? null : $item->addedByName(),
            'setlist_types' => array_map(fn (SetlistSongType $type) => $type->value, $this->setlistTypes),
            // On a do-not-play list when it was requested, even if that's changed since
            'was_do_not_play' => $item->is_do_not_play,
            'requested_at' => $item->created_at->toIso8601String(),
            'played_at' => $item->played_at?->toIso8601String(),
        ];
    }
}

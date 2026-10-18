<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalTrack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A canonical track ("song"), as listed when browsing. Expects `artist` loaded and
 * `library_tracks_count` and `libraries_count` counted.
 */
class SongResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CanonicalTrack $song */
        $song = $this->resource;

        return [
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artist === null ? null : ['id' => $song->artist->id, 'name' => $song->artist->name],
            'bpm' => $song->bpm === null ? null : (float) $song->bpm,
            'duration' => $song->formattedDuration(),
            'key' => LibraryTrackResource::key($song->key),
            'is_verified' => $song->isVerified(),
            'library_tracks_count' => (int) $song->getAttribute('library_tracks_count'),
            'libraries_count' => (int) $song->getAttribute('libraries_count'),
            'url' => route('admin.dj.songs.show', $song),
        ];
    }
}

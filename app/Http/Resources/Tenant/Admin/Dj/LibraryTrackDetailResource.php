<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\HistoryPlay;
use App\Models\Dj\LibraryTag;
use App\Models\Dj\LibraryTrack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A library track with its canonical record, the same song in other libraries, its tags and its
 * play history. Expects `canonicalTrack.artist`, `canonicalTrack.libraryTracks.library`, `tags`
 * and `historyPlays.session` loaded, and `history_plays_count` counted.
 */
class LibraryTrackDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LibraryTrack $track */
        $track = $this->resource;
        $canonical = $track->canonicalTrack;

        return [
            ...(new LibraryTrackResource($track))->toArray($request),
            'album' => $track->album,
            'label' => $track->label,
            'year' => $track->year,
            'comments' => $track->comments,
            'location' => $track->location,
            'service_track_id' => $track->service_track_id,
            'source_track_id' => $track->source_track_id,
            'missing_since' => $track->missing_since?->toIso8601String(),
            'tags' => $track->tags->map(fn (LibraryTag $tag) => [
                'name' => $tag->name,
                'category' => $tag->category,
            ])->values()->all(),
            'canonical' => $canonical === null ? null : [
                'id' => $canonical->id,
                'title' => $canonical->title,
                'artist' => $canonical->artist?->name,
                'bpm' => $canonical->bpm === null ? null : (float) $canonical->bpm,
                'duration' => $canonical->formattedDuration(),
                'key' => LibraryTrackResource::key($canonical->key),
                'is_verified' => $canonical->isVerified(),
                'is_hidden_from_requests' => $canonical->isHiddenFromRequests(),
                'is_artist_hidden_from_requests' => $canonical->artist?->isHiddenFromRequests() ?? false,
            ],
            // The same song in other libraries (or listed twice in this one)
            'other_sources' => $canonical === null ? [] : $canonical->libraryTracks
                ->reject(fn (LibraryTrack $other) => $other->is($track))
                ->map(fn (LibraryTrack $other) => [
                    'id' => $other->id,
                    'title' => $other->title,
                    'library' => $other->library->name,
                    'source' => $other->library->source->label(),
                    'is_missing' => $other->missing_since !== null,
                    'url' => route('admin.dj.tracks.show', $other),
                ])->values()->all(),
            'history_plays_count' => (int) $track->getAttribute('history_plays_count'),
            'history_plays' => $track->historyPlays->map(fn (HistoryPlay $play) => [
                'session' => $play->session->name,
                'played_at' => $play->played_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}

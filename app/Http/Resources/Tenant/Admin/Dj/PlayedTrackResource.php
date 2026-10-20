<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Enums\Dj\HistorySource;
use App\Models\Dj\HistoryPlay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A play, as part of what a gig or session played. Expects `session`, `song.artist`,
 * `track.artist` and `track.canonicalTrack` loaded.
 */
class PlayedTrackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HistoryPlay $play */
        $play = $this->resource;
        $song = $play->playedSong();
        $track = $play->track;

        return [
            'id' => $play->id,
            'title' => $play->displayTitle(),
            'artist' => $play->displayArtist(),
            'played_at' => $play->played_at?->toIso8601String(),
            'session' => $play->session->name,
            'is_manual' => $play->session->source === HistorySource::Manual,
            'song_url' => $song === null ? null : route('admin.dj.songs.show', $song),
            'track_url' => $track === null ? null : route('admin.dj.tracks.show', $track),
        ];
    }
}

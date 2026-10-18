<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\LibraryTrack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A library track, as listed when browsing
 */
class LibraryTrackResource extends JsonResource
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

        return [
            'id' => $track->id,
            'title' => $track->title,
            'artist' => $track->artist === null ? null : ['id' => $track->artist->id, 'name' => $track->artist->name],
            'library' => $track->library->name,
            'genre' => $track->genre,
            'bpm' => $track->bpm === null ? null : (float) $track->bpm,
            'duration' => $track->formattedDuration(),
            'key' => self::key($track->key),
            'play_count' => $track->play_count,
            'rating' => $track->rating,
            'date_added' => $track->date_added?->toDateString(),
            'service' => $track->service?->label(),
            'is_missing' => $track->missing_since !== null,
            'url' => route('admin.dj.tracks.show', $track),
        ];
    }

    /**
     * @return array{value: string, camelot: string, open_key: string, standard: string, label: string}|null
     */
    public static function key(?MusicalKey $key): ?array
    {
        if ($key === null) {
            return null;
        }

        return [
            'value' => $key->value,
            'camelot' => $key->camelot(),
            'open_key' => $key->openKey(),
            'standard' => $key->standard(),
            'label' => $key->label(),
        ];
    }
}

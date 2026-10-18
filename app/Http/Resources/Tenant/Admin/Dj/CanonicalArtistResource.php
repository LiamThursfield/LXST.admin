<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalArtist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A canonical artist, as listed when browsing. Expects `tracks_count` and `library_artists_count`.
 */
class CanonicalArtistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CanonicalArtist $artist */
        $artist = $this->resource;

        return [
            'id' => $artist->id,
            'name' => $artist->name,
            'is_verified' => $artist->isVerified(),
            'tracks_count' => (int) $artist->getAttribute('tracks_count'),
            'library_artists_count' => (int) $artist->getAttribute('library_artists_count'),
            'url' => route('admin.dj.canonical-artists.show', $artist),
        ];
    }
}

<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\LibraryArtist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryArtistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LibraryArtist $artist */
        $artist = $this->resource;

        return [
            'id' => $artist->id,
            'name' => $artist->name,
            'library' => $artist->library->name,
            'tracks_count' => (int) $artist->getAttribute('tracks_count'),
            'tracks_url' => route('admin.dj.tracks.index', ['filter' => ['artist_id' => $artist->id]]),
        ];
    }
}

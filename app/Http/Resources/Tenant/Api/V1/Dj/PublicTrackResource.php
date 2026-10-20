<?php

namespace App\Http\Resources\Tenant\Api\V1\Dj;

use App\Models\Dj\HistoryPlay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A track on a gig's public tracklist
 */
class PublicTrackResource extends JsonResource
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

        return [
            'id' => $play->id,
            'title' => $play->displayTitle(),
            'artist' => $play->displayArtist(),
            'played_at' => $play->played_at?->toIso8601String(),
        ];
    }
}

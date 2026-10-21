<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\HistorySession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A play history session. Expects `gig` and `library` loaded and `plays_count` counted.
 */
class HistorySessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HistorySession $session */
        $session = $this->resource;
        $gig = $session->gig;

        return [
            'id' => $session->id,
            'name' => $session->name,
            'source' => $session->source->value,
            'library' => $session->library?->name,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'plays_count' => (int) $session->getAttribute('plays_count'),
            'include_in_stats' => $session->include_in_stats,
            // A live session an imported one covers, whose plays aren't listed or counted (#159)
            'is_superseded' => $session->is_superseded,
            'gig' => $gig === null ? null : ['id' => $gig->id, 'name' => $gig->name, 'url' => route('admin.dj.gigs.edit', $gig)],
            'url' => route('admin.dj.history.show', $session),
        ];
    }
}

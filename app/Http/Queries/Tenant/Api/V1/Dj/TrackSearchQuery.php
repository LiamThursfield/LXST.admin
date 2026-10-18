<?php

namespace App\Http\Queries\Tenant\Api\V1\Dj;

use App\Models\Dj\CanonicalTrack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Library songs a visitor can request, matching a search on title or artist. Songs hidden from
 * requests, by hidden artists, or no longer in a shown library are left out (#142).
 */
class TrackSearchQuery
{
    /**
     * @return Collection<int, CanonicalTrack>
     */
    public function handle(string $search): Collection
    {
        return CanonicalTrack::query()
            ->requestable()
            ->with('artist')
            ->where(fn (Builder $query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereHas('artist', fn (Builder $artist) => $artist->whereLike('name', "%{$search}%")))
            // Titles starting with the search first, then the rest
            ->orderByRaw('case when title like ? then 0 else 1 end', ["{$search}%"])
            ->orderBy('title')
            ->limit((int) config('dj.api.search_limit'))
            ->get();
    }
}

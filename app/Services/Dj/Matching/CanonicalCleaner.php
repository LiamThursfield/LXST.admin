<?php

namespace App\Services\Dj\Matching;

use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes canonical records nothing links to any more, e.g. after a library was deleted.
 * Verified records are kept, as someone chose to keep them.
 */
class CanonicalCleaner
{
    /**
     * @return array{tracks: int, artists: int}
     */
    public function run(): array
    {
        $tracks = CanonicalTrack::query()
            ->whereNull('verified_at')
            ->whereDoesntHave('libraryTracks')
            ->delete();

        // Artists go after tracks, so artists only kept by those tracks go too
        $artists = CanonicalArtist::query()
            ->whereNull('verified_at')
            ->whereDoesntHave('libraryArtists')
            ->whereDoesntHave('tracks')
            ->delete();

        return ['tracks' => (int) $tracks, 'artists' => (int) $artists];
    }

    /**
     * Canonical tracks with no library track still present, i.e. in no library's latest import
     *
     * @param  Builder<CanonicalTrack>  $query
     * @return Builder<CanonicalTrack>
     */
    public static function orphanedTracks(Builder $query): Builder
    {
        return $query->whereDoesntHave('libraryTracks', fn (Builder $tracks) => $tracks->whereNull('missing_since'));
    }
}

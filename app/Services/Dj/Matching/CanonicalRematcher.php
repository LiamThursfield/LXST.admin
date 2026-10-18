<?php

namespace App\Services\Dj\Matching;

use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryTrack;
use Illuminate\Support\Collection;

/**
 * Applies the automatic matching rules to records already imported: refreshes their match keys,
 * merges canonical artists and tracks the rules say are the same, links unmatched library tracks,
 * and names unverified tracks by their base title. Verified records are never merged away.
 */
class CanonicalRematcher
{
    public function __construct(
        protected TrackNormaliser $normaliser,
        protected CanonicalMatcher $matcher,
        protected CanonicalMerger $merger,
    ) {}

    /**
     * @return array{artists_merged: int, tracks_merged: int, tracks_linked: int, tracks_renamed: int}
     */
    public function run(): array
    {
        $counts = ['artists_merged' => 0, 'tracks_merged' => 0, 'tracks_linked' => 0, 'tracks_renamed' => 0];

        $this->refreshKeys();

        // Artists first, so tracks by the same artist then share an artist and can be compared
        CanonicalArtist::query()->orderBy('id')->get()
            ->groupBy('match_key')
            ->each(function (Collection $artists) use (&$counts): void {
                $counts['artists_merged'] += $this->mergeGroup($artists, fn ($target, $source) => $this->merger->mergeArtists($target, $source));
            });

        CanonicalTrack::query()->orderBy('id')->get()
            ->groupBy(fn (CanonicalTrack $track) => $track->canonical_artist_id.'|'.$track->match_key)
            ->each(function (Collection $tracks) use (&$counts): void {
                $counts['tracks_merged'] += $this->mergeGroup($tracks, fn ($target, $source) => $this->merger->mergeTracks($target, $source));
            });

        $counts['tracks_merged'] += $this->mergeByStreamingId();
        $counts['tracks_linked'] = $this->linkUnmatched();
        $counts['tracks_renamed'] = $this->renameToBaseTitles();

        return $counts;
    }

    protected function refreshKeys(): void
    {
        CanonicalArtist::query()->each(function (CanonicalArtist $artist): void {
            $artist->update(['match_key' => $this->normaliser->artistKey($artist->name)]);
        });

        CanonicalTrack::query()->each(function (CanonicalTrack $track): void {
            $track->update(['match_key' => $this->normaliser->titleKey($track->title)]);
        });
    }

    /**
     * Merges every unverified record in the group into one target: the first verified record, or
     * else the one with the most library records
     *
     * @param  Collection<int, CanonicalArtist>|Collection<int, CanonicalTrack>  $group
     */
    protected function mergeGroup(Collection $group, callable $merge): int
    {
        if ($group->count() < 2) {
            return 0;
        }

        $target = $group->first(fn ($record) => $record->isVerified())
            ?? $group->sortByDesc(fn ($record) => $record instanceof CanonicalTrack
                ? $record->libraryTracks()->count()
                : $record->libraryArtists()->count())->first();

        $merged = 0;

        foreach ($group as $record) {
            if ($record->is($target) || $record->isVerified()) {
                continue;
            }

            $merge($target, $record);
            $merged++;
        }

        return $merged;
    }

    /**
     * Library tracks of the same streaming track are the same song, whatever their titles
     */
    protected function mergeByStreamingId(): int
    {
        $merged = 0;

        LibraryTrack::query()
            ->whereNotNull('service')
            ->whereNotNull('service_track_id')
            ->whereNotNull('canonical_track_id')
            ->get(['service', 'service_track_id', 'canonical_track_id'])
            ->groupBy(fn (LibraryTrack $track) => $track->service?->value.'|'.$track->service_track_id)
            ->each(function (Collection $tracks) use (&$merged): void {
                $canonicalIds = $tracks->pluck('canonical_track_id')->unique()->values();

                if ($canonicalIds->count() < 2) {
                    return;
                }

                $group = CanonicalTrack::query()->whereKey($canonicalIds->all())->orderBy('id')->get();
                $merged += $this->mergeGroup($group, fn ($target, $source) => $this->merger->mergeTracks($target, $source));
            });

        return $merged;
    }

    protected function linkUnmatched(): int
    {
        $linked = 0;

        LibraryTrack::query()->whereNull('canonical_track_id')->with('artist')->each(function (LibraryTrack $track) use (&$linked): void {
            $artist = $track->artist;

            if ($artist !== null && $artist->canonical_artist_id === null) {
                $artist->update(['canonical_artist_id' => $this->matcher->artistFor($artist->name)->id]);
            }

            $canonical = $this->matcher->trackFor(
                $track->title,
                $artist?->canonical_artist_id,
                $track->service,
                $track->service_track_id,
                $track->duration,
                $track->bpm === null ? null : (float) $track->bpm,
                $track->key,
            );

            $track->update(['canonical_track_id' => $canonical->id]);
            $linked++;
        });

        return $linked;
    }

    /**
     * Older imports named canonical tracks after a version, e.g. "Humble (Dirty)"
     */
    protected function renameToBaseTitles(): int
    {
        $renamed = 0;

        CanonicalTrack::query()->whereNull('verified_at')->each(function (CanonicalTrack $track) use (&$renamed): void {
            $base = $this->normaliser->baseTitle($track->title);

            if ($base !== $track->title) {
                $track->update(['title' => $base]);
                $renamed++;
            }
        });

        return $renamed;
    }
}

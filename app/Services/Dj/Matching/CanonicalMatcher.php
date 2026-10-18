<?php

namespace App\Services\Dj\Matching;

use App\Enums\Dj\MusicalKey;
use App\Enums\Dj\TrackService;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryTrack;

/**
 * Finds (or creates) the canonical record a library record belongs to, by the rules that are
 * safe to apply automatically: the same streaming ID, or the same artist and title once case,
 * punctuation, featured artists and version tags are ignored. Near matches are only suggested
 * (see MatchSuggestions).
 */
class CanonicalMatcher
{
    public function __construct(protected TrackNormaliser $normaliser) {}

    public function artistFor(string $name): CanonicalArtist
    {
        $key = $this->normaliser->artistKey($name);

        return CanonicalArtist::query()
            ->where('match_key', $key)
            ->orderByRaw('verified_at is null')
            ->orderBy('id')
            ->first()
            ?? CanonicalArtist::query()->create(['name' => trim($name), 'match_key' => $key]);
    }

    public function trackFor(
        string $title,
        ?int $canonicalArtistId,
        ?TrackService $service = null,
        ?string $serviceTrackId = null,
        ?int $duration = null,
        ?float $bpm = null,
        ?MusicalKey $key = null,
    ): CanonicalTrack {
        // The same streaming track is always the same song
        if ($service !== null && $serviceTrackId !== null) {
            $canonicalId = LibraryTrack::query()
                ->where('service', $service)
                ->where('service_track_id', $serviceTrackId)
                ->whereNotNull('canonical_track_id')
                ->value('canonical_track_id');

            if ($canonicalId !== null && ($canonical = CanonicalTrack::query()->find($canonicalId)) instanceof CanonicalTrack) {
                return $canonical;
            }
        }

        $matchKey = $this->normaliser->titleKey($title);

        $candidates = CanonicalTrack::query()
            ->where('canonical_artist_id', $canonicalArtistId)
            ->where('match_key', $matchKey)
            ->get();

        // Verified records first, then the closest length, to break ties
        $best = $candidates->sortBy([
            fn (CanonicalTrack $track) => $track->isVerified() ? 0 : 1,
            fn (CanonicalTrack $track) => $duration === null || $track->duration === null ? PHP_INT_MAX : abs($track->duration - $duration),
            fn (CanonicalTrack $track) => $track->id,
        ])->first();

        if ($best instanceof CanonicalTrack) {
            return $best;
        }

        return CanonicalTrack::query()->create([
            'canonical_artist_id' => $canonicalArtistId,
            'title' => $this->normaliser->baseTitle($title),
            'match_key' => $matchKey,
            'bpm' => $bpm,
            'duration' => $duration,
            'key' => $key,
        ]);
    }
}

<?php

namespace App\Services\Dj\Matching;

use App\Enums\Dj\CanonicalRecord;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\MatchDismissal;
use Illuminate\Support\Collection;

/**
 * Near matches the automatic rules don't merge, e.g. typos or slightly different spellings, for
 * someone to confirm or dismiss. Pairs that were dismissed aren't suggested again.
 */
class MatchSuggestions
{
    public function __construct(protected TrackNormaliser $normaliser) {}

    /**
     * Pairs of canonical tracks by the same artist with very similar titles, most alike first
     *
     * @return Collection<int, array{first: CanonicalTrack, second: CanonicalTrack, similarity: float}>
     */
    public function tracks(int $limit = 50): Collection
    {
        $dismissed = $this->dismissed(CanonicalRecord::Track);
        $pairs = collect();

        // Records only get a match key on import or re-match, so older ones can't be compared yet
        CanonicalTrack::query()->whereNotNull('match_key')->where('match_key', '!=', '')->with('artist')->withCount('libraryTracks')->get()
            ->groupBy('canonical_artist_id')
            ->each(function (Collection $tracks) use (&$pairs, $dismissed): void {
                $pairs = $pairs->merge($this->similarPairs($tracks->values()->all(), $dismissed));
            });

        /** @var Collection<int, array{first: CanonicalTrack, second: CanonicalTrack, similarity: float}> */
        return $pairs->sortByDesc('similarity')->take($limit)->values();
    }

    /**
     * Pairs of canonical artists with very similar names, most alike first
     *
     * @return Collection<int, array{first: CanonicalArtist, second: CanonicalArtist, similarity: float}>
     */
    public function artists(int $limit = 50): Collection
    {
        $dismissed = $this->dismissed(CanonicalRecord::Artist);
        $pairs = collect();

        // Only names starting alike are compared, which keeps this quick for large libraries
        CanonicalArtist::query()->whereNotNull('match_key')->where('match_key', '!=', '')->withCount('libraryArtists')->get()
            ->groupBy(fn (CanonicalArtist $artist) => mb_substr((string) $artist->match_key, 0, 1))
            ->each(function (Collection $artists) use (&$pairs, $dismissed): void {
                $pairs = $pairs->merge($this->similarPairs($artists->values()->all(), $dismissed));
            });

        /** @var Collection<int, array{first: CanonicalArtist, second: CanonicalArtist, similarity: float}> */
        return $pairs->sortByDesc('similarity')->take($limit)->values();
    }

    /**
     * Records of one kind whose match keys are alike, but not the same
     *
     * @param  array<int, CanonicalArtist|CanonicalTrack>  $records
     * @param  array<string, true>  $dismissed
     * @return list<array{first: CanonicalArtist|CanonicalTrack, second: CanonicalArtist|CanonicalTrack, similarity: float}>
     */
    protected function similarPairs(array $records, array $dismissed): array
    {
        $records = array_values($records);
        $threshold = (float) config('dj.matching.suggestion_threshold');
        $pairs = [];

        for ($i = 0; $i < count($records); $i++) {
            for ($j = $i + 1; $j < count($records); $j++) {
                $first = $records[$i];
                $second = $records[$j];
                $firstKey = (string) $first->match_key;
                $secondKey = (string) $second->match_key;

                // Equal keys are the automatic rules' job, very different lengths can't be close,
                // and numbered series (Part 1, Part 2) aren't typos
                if ($firstKey === $secondKey
                    || abs(strlen($firstKey) - strlen($secondKey)) > 4
                    || $this->normaliser->differOnlyInNumbers($firstKey, $secondKey)) {
                    continue;
                }

                if (isset($dismissed[min($first->id, $second->id).'|'.max($first->id, $second->id)])) {
                    continue;
                }

                $similarity = $this->normaliser->similarity($firstKey, $secondKey);

                if ($similarity >= $threshold) {
                    $pairs[] = ['first' => $first, 'second' => $second, 'similarity' => round($similarity, 2)];
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, true>
     */
    protected function dismissed(CanonicalRecord $record): array
    {
        return MatchDismissal::query()
            ->where('record', $record)
            ->get(['first_id', 'second_id'])
            ->mapWithKeys(fn (MatchDismissal $dismissal) => [$dismissal->first_id.'|'.$dismissal->second_id => true])
            ->all();
    }
}

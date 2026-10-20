<?php

namespace App\Services\Dj;

use App\Enums\Dj\HistorySource;
use App\Models\Dj\Gig;
use App\Models\Dj\HistorySession;
use App\Services\Dj\Api\DjApiCache;
use Carbon\CarbonImmutable;

/**
 * Hides a gig's live session (#124) once an imported session linked to the same gig covers its
 * time, so the same set isn't listed or counted twice (#159). Rekordbox's imported history is the
 * complete record, so it wins; History still shows both, and unlinking the import brings the live
 * session back.
 */
class LiveHistoryCover
{
    public function __construct(protected DjApiCache $cache) {}

    /**
     * Works out again which of the gigs' live sessions are covered
     *
     * @param  iterable<int|null>  $gigIds
     */
    public function refresh(iterable $gigIds): void
    {
        $gigIds = array_values(array_unique(array_filter(is_array($gigIds) ? $gigIds : iterator_to_array($gigIds))));

        if ($gigIds === []) {
            return;
        }

        $slack = (int) config('dj.history.live_overlap_minutes');
        $sessions = HistorySession::query()
            ->whereIn('gig_id', $gigIds)
            ->whereNotNull('started_at')
            ->get()
            ->groupBy('gig_id');

        foreach ($sessions as $gigSessions) {
            $imported = $gigSessions->where('source', HistorySource::Import);

            foreach ($gigSessions->where('source', HistorySource::Cli) as $live) {
                /** @var CarbonImmutable $liveStart */
                $liveStart = $live->started_at;
                $liveEnd = $live->ended_at ?? $liveStart;

                $covered = $imported->contains(function (HistorySession $import) use ($liveStart, $liveEnd, $slack): bool {
                    /** @var CarbonImmutable $start */
                    $start = $import->started_at;
                    $end = $import->ended_at ?? $start;

                    return $start->subMinutes($slack)->lessThanOrEqualTo($liveEnd)
                        && $end->addMinutes($slack)->greaterThanOrEqualTo($liveStart);
                });

                if ($live->is_superseded !== $covered) {
                    $live->update(['is_superseded' => $covered]);
                    // The gig's public tracklist changes (#125); imports run in the queue, outside admin requests
                    $gig = Gig::query()->whereKey($live->gig_id)->first();

                    if ($gig !== null) {
                        $this->cache->refreshGig($gig);
                    }
                }
            }
        }

        // Live sessions that lost their gig (unlinked) aren't covered by anything
        HistorySession::query()->whereNull('gig_id')->where('is_superseded', true)->update(['is_superseded' => false]);
    }
}

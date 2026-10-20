<?php

namespace App\Http\Queries\Tenant\Api\V1\Dj;

use App\Data\Dj\EffectiveRequestSettings;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What a gig played, newest first, from all the history linked to it (live, imported and added by
 * hand, #118), held back by the gig's tracklist delay
 */
class GigTracklistQuery
{
    /**
     * @return LengthAwarePaginator<int, HistoryPlay>
     */
    public function handle(Gig $gig, EffectiveRequestSettings $settings, int $page = 1): LengthAwarePaginator
    {
        return $this->plays($gig, $settings)
            ->with(['track.artist', 'song.artist'])
            ->latest('played_at')
            ->latest('id')
            ->paginate($settings->tracklist_per_page, ['*'], 'page', $page);
    }

    /**
     * The track playing now: the latest, if it started recently enough to still be on
     */
    public function nowPlaying(Gig $gig, EffectiveRequestSettings $settings): ?HistoryPlay
    {
        $latest = $this->plays($gig, $settings)
            ->with(['track.artist', 'song.artist'])
            ->latest('played_at')
            ->latest('id')
            ->first();

        $shownUntil = $latest?->played_at?->addMinutes($settings->tracklist_delay_minutes + (int) config('dj.now_playing.current_minutes'));

        return $shownUntil !== null && $shownUntil->isFuture() ? $latest : null;
    }

    /**
     * @return Builder<HistoryPlay>
     */
    protected function plays(Gig $gig, EffectiveRequestSettings $settings): Builder
    {
        return HistoryPlay::query()
            ->whereHas('session', fn (Builder $sessions) => $sessions->where('gig_id', $gig->id))
            ->whereNotNull('played_at')
            ->where('played_at', '<=', CarbonImmutable::now()->subMinutes($settings->tracklist_delay_minutes));
    }
}

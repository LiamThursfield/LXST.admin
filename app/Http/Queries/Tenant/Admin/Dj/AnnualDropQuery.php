<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Data\Dj\AnnualDropPlay;
use App\Enums\Dj\MusicalKey;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\SongListItem;
use App\Services\Dj\Matching\TrackNormaliser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Annual Drop (#127): a wrap-up of everything played in a period. Counts plays in sessions in
 * stats, leaving out live sessions an import covers (#159), from gigs only or from all history.
 */
class AnnualDropQuery
{
    /** How many top artists and tracks are listed */
    protected const int TOP = 10;

    /** The longest a play counts for towards hours played, in minutes */
    protected const int MAX_TRACK_MINUTES = 10;

    /** The width of a BPM range, e.g. 120–124 */
    public const int BPM_RANGE = 4;

    public function __construct(protected TrackNormaliser $normaliser) {}

    /**
     * @return array{
     *     totals: array{plays: int, songs: int, gigs: int, hours: float, requests: int},
     *     top_artists: list<array{name: string, plays: int}>,
     *     top_tracks: list<array{title: string, artist: string|null, plays: int}>,
     *     bpm: array{most_played: int|null, most_played_count: int, range_from: int|null, range_to: int|null, range_count: int, average: float|null},
     *     key: array{camelot: string, label: string, plays: int}|null
     * }
     */
    public function handle(CarbonImmutable $from, CarbonImmutable $to, bool $gigsOnly): array
    {
        $plays = $this->plays($from, $to, $gigsOnly);

        return [
            'totals' => [
                'plays' => $plays->count(),
                'songs' => $plays->map(fn (AnnualDropPlay $play) => $play->songKey)->unique()->count(),
                'gigs' => $plays->map(fn (AnnualDropPlay $play) => $play->gigId)->filter()->unique()->count(),
                'hours' => $this->hours($plays),
                'requests' => $this->requests($from, $to),
            ],
            'top_artists' => array_values($plays
                ->filter(fn (AnnualDropPlay $play) => $play->artistKey !== null)
                ->groupBy(fn (AnnualDropPlay $play) => (string) $play->artistKey)
                ->map(fn (Collection $group) => ['name' => (string) $group->first()?->artistName, 'plays' => $group->count()])
                ->sortByDesc('plays')
                ->take(self::TOP)
                ->all()),
            'top_tracks' => array_values($plays
                ->groupBy(fn (AnnualDropPlay $play) => $play->songKey)
                ->map(fn (Collection $group) => [
                    'title' => (string) $group->first()?->title,
                    'artist' => $group->first()?->artistName,
                    'plays' => $group->count(),
                ])
                ->sortByDesc('plays')
                ->take(self::TOP)
                ->all()),
            'bpm' => $this->bpm($plays),
            'key' => $this->key($plays),
        ];
    }

    /**
     * The years that have counted plays, newest first, for the year picker
     *
     * @return list<int>
     */
    public function years(): array
    {
        return array_values($this->countedPlays(false)
            ->whereNotNull('played_at')
            ->toBase()
            ->selectRaw('distinct year(played_at) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all());
    }

    /**
     * Each play with what it was (song, artist), its BPM and key, from the library track or the song
     *
     * @return Collection<int, AnnualDropPlay>
     */
    protected function plays(CarbonImmutable $from, CarbonImmutable $to, bool $gigsOnly): Collection
    {
        return $this->countedPlays($gigsOnly)
            ->whereBetween('dj_history_plays.played_at', [$from, $to])
            ->leftJoin('dj_library_tracks', 'dj_library_tracks.id', '=', 'dj_history_plays.library_track_id')
            ->leftJoin('dj_library_artists', 'dj_library_artists.id', '=', 'dj_library_tracks.library_artist_id')
            ->leftJoin('dj_canonical_tracks', 'dj_canonical_tracks.id', '=', DB::raw('coalesce(dj_history_plays.canonical_track_id, dj_library_tracks.canonical_track_id)'))
            ->leftJoin('dj_canonical_artists', 'dj_canonical_artists.id', '=', 'dj_canonical_tracks.canonical_artist_id')
            ->join('dj_history_sessions', 'dj_history_sessions.id', '=', 'dj_history_plays.history_session_id')
            ->toBase()
            ->select([
                'dj_history_plays.history_session_id as session_id',
                'dj_history_sessions.gig_id',
                'dj_history_plays.played_at',
                'dj_canonical_tracks.id as canonical_id',
                'dj_canonical_artists.id as canonical_artist_id',
                DB::raw('coalesce(dj_canonical_tracks.title, dj_library_tracks.title, dj_history_plays.title) as title'),
                DB::raw('coalesce(dj_canonical_artists.name, dj_library_artists.name, dj_history_plays.artist) as artist_name'),
                DB::raw('coalesce(dj_library_tracks.bpm, dj_canonical_tracks.bpm) as bpm'),
                DB::raw('coalesce(dj_library_tracks.`key`, dj_canonical_tracks.`key`) as `key`'),
            ])
            ->get()
            ->map(fn (object $row): AnnualDropPlay => new AnnualDropPlay(
                songKey: $row->canonical_id !== null
                    ? 'track:'.$row->canonical_id
                    : 'name:'.$this->normaliser->artistKey($row->artist_name).'|'.$this->normaliser->titleKey((string) $row->title),
                title: $row->title,
                artistKey: $row->canonical_artist_id !== null
                    ? 'artist:'.$row->canonical_artist_id
                    : ($row->artist_name === null ? null : 'name:'.$this->normaliser->artistKey($row->artist_name)),
                artistName: $row->artist_name,
                bpm: $row->bpm === null ? null : (float) $row->bpm,
                key: $row->key,
                gigId: $row->gig_id === null ? null : (int) $row->gig_id,
                sessionId: (int) $row->session_id,
                playedAt: CarbonImmutable::parse($row->played_at),
            ));
    }

    /**
     * Plays in sessions in stats, not covered by an import, and (for gigs only) linked to a gig
     *
     * @return Builder<HistoryPlay>
     */
    protected function countedPlays(bool $gigsOnly): Builder
    {
        return HistoryPlay::query()
            ->whereHas('session', fn (Builder $sessions) => $sessions
                ->where('include_in_stats', true)
                ->counted()
                ->when($gigsOnly, fn (Builder $sessions) => $sessions->whereNotNull('gig_id')));
    }

    /**
     * Hours played: the time from each play to the next in its session, each counted up to a track's
     * length at most, so a session left open between sets doesn't count as playing
     *
     * @param  Collection<int, AnnualDropPlay>  $plays
     */
    protected function hours(Collection $plays): float
    {
        $cap = self::MAX_TRACK_MINUTES * 60;

        $seconds = $plays->groupBy(fn (AnnualDropPlay $play) => $play->sessionId)->sum(function (Collection $session) use ($cap): int {
            $times = $session
                ->map(fn (AnnualDropPlay $play) => $play->playedAt->getTimestamp())
                ->sort()
                ->values();

            $total = 0;

            foreach ($times as $index => $time) {
                $next = $times[$index + 1] ?? null;
                // The last track's length isn't known from the history, so it counts as a full one
                $total += $next === null ? $cap : min($cap, $next - $time);
            }

            return $total;
        });

        return round($seconds / 3600, 1);
    }

    /**
     * Requests received for gigs in the period
     */
    protected function requests(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return SongListItem::query()
            ->whereNotNull('status')
            ->whereHas('songList', fn (Builder $lists) => $lists->whereIn(
                'gig_id',
                Gig::query()->whereBetween('starts_at', [$from, $to])->select('id'),
            ))
            ->count();
    }

    /**
     * The most played BPM (rounded), the most played 4-BPM range, and the average
     *
     * @param  Collection<int, AnnualDropPlay>  $plays
     * @return array{most_played: int|null, most_played_count: int, range_from: int|null, range_to: int|null, range_count: int, average: float|null}
     */
    protected function bpm(Collection $plays): array
    {
        $bpms = $plays->map(fn (AnnualDropPlay $play) => $play->bpm)->filter();

        if ($bpms->isEmpty()) {
            return ['most_played' => null, 'most_played_count' => 0, 'range_from' => null, 'range_to' => null, 'range_count' => 0, 'average' => null];
        }

        $byBpm = $bpms->countBy(fn (float $bpm) => (int) round($bpm))->sortDesc();
        $byRange = $bpms->countBy(fn (float $bpm) => (int) (floor($bpm / self::BPM_RANGE) * self::BPM_RANGE))->sortDesc();
        $rangeFrom = (int) $byRange->keys()->first();

        return [
            'most_played' => (int) $byBpm->keys()->first(),
            'most_played_count' => (int) $byBpm->first(),
            'range_from' => $rangeFrom,
            'range_to' => $rangeFrom + self::BPM_RANGE,
            'range_count' => (int) $byRange->first(),
            'average' => round($bpms->avg() ?? 0, 1),
        ];
    }

    /**
     * The most played key
     *
     * @param  Collection<int, AnnualDropPlay>  $plays
     * @return array{camelot: string, label: string, plays: int}|null
     */
    protected function key(Collection $plays): ?array
    {
        $byKey = $plays->map(fn (AnnualDropPlay $play) => $play->key)->filter()->countBy()->sortDesc();
        $key = MusicalKey::tryFrom((string) $byKey->keys()->first());

        return $key === null ? null : ['camelot' => $key->camelot(), 'label' => $key->label(), 'plays' => (int) $byKey->first()];
    }
}

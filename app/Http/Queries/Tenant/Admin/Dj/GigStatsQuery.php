<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Enums\Dj\MusicalKey;
use App\Enums\Dj\RequestStatus;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\SongListItem;
use App\Services\Dj\Requests\SongKeys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A gig's stats (#126): what was played (sessions in stats, leaving out a live session an import
 * covers, #159) and how requests went
 */
class GigStatsQuery
{
    /** How many entries the "most" lists show */
    protected const int TOP = 10;

    public function __construct(protected SongKeys $keys) {}

    /**
     * @return array{
     *     plays: array{count: int, songs: int, started_at: string|null, ended_at: string|null, minutes: int|null},
     *     bpm: array{series: list<array{played_at: string, bpm: float, title: string, artist: string|null}>, average: float|null, min: float|null, max: float|null},
     *     keys: list<array{camelot: string, label: string, count: int}>,
     *     requests: array{received: int, votes: int, statuses: array<string, int>, do_not_play: int},
     *     most_requested: list<array{title: string, artist: string|null, votes: int, status: string|null, in_library: bool}>,
     *     to_buy: list<array{title: string, artist: string|null, count: int, votes: int, played: bool}>
     * }
     */
    public function handle(Gig $gig): array
    {
        $plays = $this->plays($gig);
        $requests = $gig->requestsList === null ? new Collection : SongListItem::query()
            ->where('song_list_id', $gig->requestsList->id)
            ->whereNotNull('status')
            ->with('song.artist')
            ->withCount('votes')
            ->get();

        return [
            'plays' => $this->playTotals($plays),
            'bpm' => $this->bpm($plays),
            'keys' => $this->keyCounts($plays),
            'requests' => [
                'received' => $requests->count(),
                'votes' => (int) $requests->sum('votes_count'),
                'statuses' => collect(RequestStatus::cases())
                    ->mapWithKeys(fn (RequestStatus $status) => [$status->value => $requests->where('status', $status)->count()])
                    ->all(),
                'do_not_play' => $requests->where('is_do_not_play', true)->count(),
            ],
            'most_requested' => array_values($requests
                ->sortBy([['votes_count', 'desc'], ['created_at', 'asc']])
                ->take(self::TOP)
                ->map(fn (SongListItem $item) => [
                    'title' => $item->title(),
                    'artist' => $item->artist(),
                    'votes' => (int) $item->getAttribute('votes_count'),
                    'status' => $item->status?->value,
                    'in_library' => $item->canonical_track_id !== null,
                ])
                ->all()),
            'to_buy' => $this->toBuy($requests),
        ];
    }

    /**
     * The gig's plays that count for stats, in time order
     *
     * @return Collection<int, HistoryPlay>
     */
    protected function plays(Gig $gig): Collection
    {
        return HistoryPlay::query()
            ->whereHas('session', fn (Builder $sessions) => $sessions
                ->where('gig_id', $gig->id)
                ->where('include_in_stats', true)
                ->counted())
            ->whereNotNull('played_at')
            ->with(['track.artist', 'track.canonicalTrack', 'song.artist'])
            ->orderBy('played_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, HistoryPlay>  $plays
     * @return array{count: int, songs: int, started_at: string|null, ended_at: string|null, minutes: int|null}
     */
    protected function playTotals(Collection $plays): array
    {
        $first = $plays->first()?->played_at;
        $last = $plays->last()?->played_at;

        return [
            'count' => $plays->count(),
            'songs' => $plays->map(fn (HistoryPlay $play) => $this->keys->key(
                $play->playedSong()?->id,
                $play->displayArtist(),
                $play->displayTitle(),
            ))->unique()->count(),
            'started_at' => $first?->toIso8601String(),
            'ended_at' => $last?->toIso8601String(),
            'minutes' => $first === null || $last === null ? null : (int) $first->diffInMinutes($last),
        ];
    }

    /**
     * BPM over time, from the library track or the song, for plays that have one
     *
     * @param  Collection<int, HistoryPlay>  $plays
     * @return array{series: list<array{played_at: string, bpm: float, title: string, artist: string|null}>, average: float|null, min: float|null, max: float|null}
     */
    protected function bpm(Collection $plays): array
    {
        $series = [];

        foreach ($plays as $play) {
            $bpm = $play->track->bpm ?? $play->playedSong()?->bpm;

            if ($bpm !== null && $play->played_at !== null) {
                $series[] = [
                    'played_at' => $play->played_at->toIso8601String(),
                    'bpm' => round((float) $bpm, 1),
                    'title' => $play->displayTitle(),
                    'artist' => $play->displayArtist(),
                ];
            }
        }

        $values = array_column($series, 'bpm');

        return [
            'series' => $series,
            'average' => $values === [] ? null : round(array_sum($values) / count($values), 1),
            'min' => $values === [] ? null : min($values),
            'max' => $values === [] ? null : max($values),
        ];
    }

    /**
     * How often each key was played, most played first
     *
     * @param  Collection<int, HistoryPlay>  $plays
     * @return list<array{camelot: string, label: string, count: int}>
     */
    protected function keyCounts(Collection $plays): array
    {
        return array_values($plays
            ->map(fn (HistoryPlay $play) => $play->track->key ?? $play->playedSong()?->key)
            ->filter()
            ->countBy(fn (MusicalKey $key) => $key->value)
            ->sortDesc()
            ->map(fn (int $count, string $value) => [
                'camelot' => MusicalKey::from($value)->camelot(),
                'label' => MusicalKey::from($value)->label(),
                'count' => $count,
            ])
            ->all());
    }

    /**
     * Custom requests (not in the library), grouped as the same song, most asked first: a
     * shopping list
     *
     * @param  Collection<int, SongListItem>  $requests
     * @return list<array{title: string, artist: string|null, count: int, votes: int, played: bool}>
     */
    protected function toBuy(Collection $requests): array
    {
        return array_values($requests
            ->whereNull('canonical_track_id')
            ->groupBy(fn (SongListItem $item) => $this->keys->forItem($item))
            ->map(fn (Collection $items) => [
                'title' => $items->first()?->title() ?? '',
                'artist' => $items->first()?->artist(),
                'count' => $items->count(),
                'votes' => (int) $items->sum('votes_count'),
                'played' => $items->contains(fn (SongListItem $item) => $item->status === RequestStatus::Played),
            ])
            ->sortBy([['count', 'desc'], ['votes', 'desc']])
            ->take(self::TOP)
            ->all());
    }
}

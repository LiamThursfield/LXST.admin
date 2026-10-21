<?php

namespace App\Services\Dj;

use App\Enums\Dj\HistorySource;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Settings\DjSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * Links play history to gigs. A gig's played tracks are its linked sessions' plays, so nothing
 * is copied and re-imports keep working. Sessions can stay unlinked, e.g. practice at home.
 */
class HistoryLinker
{
    public function __construct(
        protected DjSettings $settings,
        protected LiveHistoryCover $cover,
    ) {}

    /**
     * @param  Collection<int, HistorySession>  $sessions
     */
    public function link(Collection $sessions, Gig $gig): void
    {
        $affectedGigIds = [$gig->id, ...$this->gigIds($sessions)];

        HistorySession::query()->whereKey($sessions->modelKeys())->update(['gig_id' => $gig->id]);
        $this->cover->refresh($affectedGigIds);
    }

    /**
     * @param  Collection<int, HistorySession>  $sessions
     */
    public function unlink(Collection $sessions): void
    {
        $affectedGigIds = $this->gigIds($sessions);

        HistorySession::query()->whereKey($sessions->modelKeys())->update(['gig_id' => null]);
        $this->cover->refresh($affectedGigIds);
    }

    /**
     * A new gig from the first play to the last, in the default timezone, linked to the sessions
     *
     * @param  Collection<int, HistorySession>  $sessions
     */
    public function createGig(Collection $sessions, ?string $name = null): Gig
    {
        return DB::transaction(function () use ($sessions, $name): Gig {
            $timezone = $this->settings->default_timezone;
            $startsAt = $sessions->min('started_at') ?? CarbonImmutable::now();
            $endsAt = $sessions->max('ended_at') ?? $startsAt;

            // A gig needs to end after it starts, even for a single play
            if (! $endsAt->greaterThan($startsAt)) {
                $endsAt = $startsAt->addMinutes(5);
            }

            $gig = Gig::query()->create([
                'name' => trim((string) $name) !== '' ? trim((string) $name) : __('dj.history.gig_name', [
                    'date' => CarbonImmutable::instance($startsAt)->setTimezone($timezone)->format('j M Y'),
                ]),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezone,
            ]);

            $this->link($sessions, $gig);

            return $gig;
        });
    }

    /**
     * @param  Collection<int, HistorySession>  $sessions
     */
    public function setIncludeInStats(Collection $sessions, bool $include): void
    {
        HistorySession::query()->whereKey($sessions->modelKeys())->update(['include_in_stats' => $include]);
    }

    /**
     * Adds a song to a gig's played tracks by hand, in the gig's manual session
     */
    public function addManualPlay(Gig $gig, CanonicalTrack $song, CarbonImmutable $playedAt): HistoryPlay
    {
        return DB::transaction(function () use ($gig, $song, $playedAt): HistoryPlay {
            $session = HistorySession::query()->firstOrCreate(
                ['gig_id' => $gig->id, 'source' => HistorySource::Manual],
                ['name' => __('dj.history.manual_session'), 'include_in_stats' => true],
            );

            $play = $session->plays()->create([
                'canonical_track_id' => $song->id,
                'position' => (int) $session->plays()->max('position') + 1,
                'played_at' => $playedAt,
            ]);

            $this->refreshTimes($session);

            return $play;
        });
    }

    /**
     * Only plays added by hand can be removed; imported plays come back on a re-import, so
     * their session is unlinked instead
     */
    public function removeManualPlay(HistoryPlay $play): void
    {
        $session = $play->session;

        $play->delete();

        if (! $session->plays()->exists()) {
            $session->delete();

            return;
        }

        $this->refreshTimes($session);
    }

    /**
     * Unlinked sessions on during a gig, to link to it
     *
     * @return BaseCollection<int, array{session: HistorySession, gig: Gig}>
     */
    public function suggestedLinks(int $limit = 20): BaseCollection
    {
        $sessions = HistorySession::query()
            ->whereNull('gig_id')
            ->whereNotNull('started_at')
            ->where('include_in_stats', true)
            ->withCount('plays')
            ->latest('started_at')
            ->get();

        $gigs = Gig::query()->latest('starts_at')->get();

        return $sessions
            ->map(function (HistorySession $session) use ($gigs): ?array {
                $gig = $this->gigDuring($gigs, $session);

                return $gig === null ? null : ['session' => $session, 'gig' => $gig];
            })
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * Unlinked sessions close together that look like one gig (Rekordbox starts a new session
     * whenever it's relaunched), newest first. Sessions during an existing gig are left to
     * suggestedLinks().
     *
     * @return BaseCollection<int, Collection<int, HistorySession>>
     */
    public function suggestedGigs(int $limit = 20): BaseCollection
    {
        $gap = (int) config('dj.history.gig_gap_minutes');
        $gigs = Gig::query()->latest('starts_at')->get();

        $sessions = HistorySession::query()
            ->whereNull('gig_id')
            ->whereNotNull('started_at')
            ->where('include_in_stats', true)
            ->withCount('plays')
            ->oldest('started_at')
            ->get()
            ->reject(fn (HistorySession $session) => $this->gigDuring($gigs, $session) !== null);

        /** @var BaseCollection<int, Collection<int, HistorySession>> $groups */
        $groups = collect();
        /** @var Collection<int, HistorySession> $current */
        $current = new Collection;

        foreach ($sessions as $session) {
            /** @var HistorySession|null $previous */
            $previous = $current->last();
            /** @var CarbonImmutable $startedAt */
            $startedAt = $session->started_at;

            if ($previous !== null && ($previous->ended_at ?? $previous->started_at ?? $startedAt)->diffInMinutes($startedAt) > $gap) {
                $groups->push($current);
                $current = new Collection;
            }

            $current->push($session);
        }

        if ($current->isNotEmpty()) {
            $groups->push($current);
        }

        return $groups->reverse()->take($limit)->values();
    }

    /**
     * The latest of the gigs that overlaps the session, worked out in memory so suggestions
     * don't query once per session
     *
     * @param  Collection<int, Gig>  $gigs
     */
    protected function gigDuring(Collection $gigs, HistorySession $session): ?Gig
    {
        /** @var CarbonImmutable $startedAt */
        $startedAt = $session->started_at;
        $endedAt = $session->ended_at ?? $startedAt->addMinute();

        return $gigs->first(fn (Gig $gig) => $gig->starts_at->lessThan($endedAt) && $gig->ends_at->greaterThan($startedAt));
    }

    /**
     * The gigs the sessions are linked to now, from the database rather than the models, which can be stale
     *
     * @param  Collection<int, HistorySession>  $sessions
     * @return list<int>
     */
    protected function gigIds(Collection $sessions): array
    {
        return array_values(HistorySession::query()->whereKey($sessions->modelKeys())->whereNotNull('gig_id')->distinct()->pluck('gig_id')->all());
    }

    protected function refreshTimes(HistorySession $session): void
    {
        $session->update([
            'started_at' => $session->plays()->min('played_at'),
            'ended_at' => $session->plays()->max('played_at'),
        ]);
    }
}

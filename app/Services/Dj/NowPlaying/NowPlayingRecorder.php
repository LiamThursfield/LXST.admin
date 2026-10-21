<?php

namespace App\Services\Dj\NowPlaying;

use App\Data\Dj\NowPlayingData;
use App\Enums\Dj\HistorySource;
use App\Enums\Dj\RequestStatus;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\SongListItem;
use App\Services\Dj\Api\DjApiCache;
use App\Services\Dj\LiveHistoryCover;
use App\Services\Dj\Matching\TrackNormaliser;
use App\Services\Dj\Requests\SongKeys;
use Illuminate\Support\Facades\DB;

/**
 * Adds a track the CLI tool reported to the gig's played tracks, in its live history session
 * (#118), and marks requests for the same song as played (#120)
 */
class NowPlayingRecorder
{
    public function __construct(
        protected TrackNormaliser $normaliser,
        protected SongKeys $keys,
        protected DjApiCache $cache,
        protected LiveHistoryCover $cover,
    ) {}

    /**
     * @return array{play: HistoryPlay, duplicate: bool, requests_played: int}
     */
    public function record(Gig $gig, NowPlayingData $report): array
    {
        return DB::transaction(function () use ($gig, $report): array {
            $session = HistorySession::query()->firstOrCreate(
                ['gig_id' => $gig->id, 'source' => HistorySource::Cli],
                ['name' => __('dj.history.live_session'), 'include_in_stats' => true],
            );
            // One report at a time per session, so duplicates and positions hold
            HistorySession::query()->whereKey($session->id)->lockForUpdate()->first();

            [$libraryTrack, $song] = $this->match($report);

            $duplicate = $this->recentPlay($session, $report, $libraryTrack, $song);

            if ($duplicate !== null) {
                return ['play' => $duplicate, 'duplicate' => true, 'requests_played' => 0];
            }

            $play = $session->plays()->create([
                'library_track_id' => $libraryTrack?->id,
                'canonical_track_id' => $song?->id,
                'source_track_id' => $report->source_track_id,
                'artist' => $report->artist,
                'title' => $report->title,
                'position' => (int) $session->plays()->max('position') + 1,
                'played_at' => $report->played_at,
            ]);

            $session->update([
                'started_at' => $session->plays()->min('played_at'),
                'ended_at' => $session->plays()->max('played_at'),
            ]);

            // A live session can start (or grow) into a time an imported session already covers
            $this->cover->refresh([$gig->id]);
            $requestsPlayed = $this->markRequestsPlayed($gig, $report, $song);
            $this->cache->refreshGig($gig);

            return ['play' => $play, 'duplicate' => false, 'requests_played' => $requestsPlayed];
        });
    }

    /**
     * The library track by its source ID, or else the song by normalised artist and title
     *
     * @return array{0: LibraryTrack|null, 1: CanonicalTrack|null}
     */
    protected function match(NowPlayingData $report): array
    {
        $libraryTrack = $report->source_track_id === null ? null : LibraryTrack::query()
            ->where('source_track_id', $report->source_track_id)
            ->when($report->library_id !== null, fn ($tracks) => $tracks->where('library_id', $report->library_id))
            ->orderByRaw('missing_since is not null')
            ->orderBy('id')
            ->first();

        $song = $libraryTrack->canonicalTrack ?? CanonicalTrack::query()
            ->where('match_key', $this->normaliser->titleKey($report->title))
            ->whereIn('canonical_artist_id', CanonicalArtist::query()->where('match_key', $this->normaliser->artistKey($report->artist))->select('id'))
            ->orderByRaw('verified_at is null')
            ->orderBy('id')
            ->first();

        return [$libraryTrack, $song];
    }

    /**
     * The same track reported within the duplicate window, e.g. a retry or the other deck
     */
    protected function recentPlay(HistorySession $session, NowPlayingData $report, ?LibraryTrack $libraryTrack, ?CanonicalTrack $song): ?HistoryPlay
    {
        $window = (int) config('dj.now_playing.duplicate_minutes');
        $key = $this->keys->key($song?->id, $report->artist, $report->title);

        return $session->plays()
            ->whereBetween('played_at', [$report->played_at->subMinutes($window), $report->played_at->addMinutes($window)])
            ->with('track')
            ->get()
            ->first(fn (HistoryPlay $play) => ($libraryTrack !== null && $play->library_track_id === $libraryTrack->id)
                || $this->keys->key($play->canonical_track_id ?? $play->track?->canonical_track_id, $play->artist, (string) $play->title) === $key);
    }

    /**
     * Pending or accepted requests for the same song: the same library song, or a custom
     * request matching the reported artist and title
     */
    protected function markRequestsPlayed(Gig $gig, NowPlayingData $report, ?CanonicalTrack $song): int
    {
        $list = $gig->requestsList;

        if ($list === null) {
            return 0;
        }

        $keys = array_filter([
            $song === null ? null : $this->keys->key($song->id, null, null),
            $this->keys->key(null, $report->artist, $report->title),
        ]);

        $ids = SongListItem::query()
            ->where('song_list_id', $list->id)
            ->whereIn('status', RequestStatus::open())
            ->get()
            ->filter(fn (SongListItem $item) => in_array($this->keys->forItem($item), $keys, true))
            ->modelKeys();

        return SongListItem::query()->whereKey($ids)->update([
            'status' => RequestStatus::Played,
            'played_at' => $report->played_at,
        ]);
    }
}

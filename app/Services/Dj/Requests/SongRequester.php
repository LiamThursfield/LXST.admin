<?php

namespace App\Services\Dj\Requests;

use App\Data\Dj\SongRequestData;
use App\Enums\Dj\RequestOutcome;
use App\Enums\Dj\RequestRefusal;
use App\Enums\Dj\RequestStatus;
use App\Enums\Dj\SetlistSongType;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Dj\Api\DjApiCache;
use App\Services\Dj\Matching\TrackNormaliser;
use App\Services\Dj\Requests\Exceptions\RequestRefused;
use App\Services\Dj\SongLists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Takes a visitor's song request for a gig, as a song on the gig's requests list (#146). A request
 * for a song already pending or accepted becomes an upvote instead, one per visitor.
 */
class SongRequester
{
    public function __construct(
        protected SongLists $songLists,
        protected TrackNormaliser $normaliser,
        protected RequestAccess $access,
        protected RequestVotes $votes,
        protected DjApiCache $cache,
        protected SongKeys $keys,
    ) {}

    /**
     * @return array{outcome: RequestOutcome, item: SongListItem}
     *
     * @throws RequestRefused
     */
    public function submit(Gig $gig, SongRequestData $request): array
    {
        $settings = $gig->requestSettings();

        if (! $gig->requestsOpenAt(now())) {
            throw new RequestRefused(RequestRefusal::Closed);
        }

        $this->access->ensureAllowed($gig, $request->guest_pass);
        $list = $this->songLists->requestsListFor($gig);

        $song = $this->song($request);

        if ($song['canonical_track_id'] === null && ! $settings->allow_custom_requests) {
            throw new RequestRefused(RequestRefusal::CustomNotAllowed);
        }

        return DB::transaction(function () use ($gig, $list, $request, $song, $settings): array {
            // One request at a time per list, so limits and duplicates hold under load
            SongList::query()->whereKey($list->id)->lockForUpdate()->first();

            $existing = $this->openDuplicate($list, $song);

            if ($existing !== null) {
                return [
                    'outcome' => $this->votes->add($existing, $request->visitor_hash, $request->guest_pass) ? RequestOutcome::Upvoted : RequestOutcome::AlreadyRequested,
                    'item' => $existing,
                ];
            }

            if ($settings->max_requests_per_visitor > 0 && $request->visitor_hash !== null
                && $list->items()->where('visitor_hash', $request->visitor_hash)->count() >= $settings->max_requests_per_visitor) {
                throw new RequestRefused(RequestRefusal::VisitorLimit);
            }

            if ($settings->max_pending_requests > 0
                && $list->items()->where('status', RequestStatus::Pending)->count() >= $settings->max_pending_requests) {
                throw new RequestRefused(RequestRefusal::QueueFull);
            }

            $item = $this->songLists->add($list, [
                ...$song,
                'status' => RequestStatus::Pending,
                'requester_name' => $request->requester_name,
                'show_requester_name' => $request->show_requester_name,
                'message' => $request->message,
                'visitor_hash' => $request->visitor_hash,
                'guest_pass_id' => $request->guest_pass?->id,
                'is_do_not_play' => $this->isDoNotPlay($gig, $song),
            ]);
            $this->votes->add($item, $request->visitor_hash, $request->guest_pass);
            $this->cache->refreshRequests($gig);

            return ['outcome' => RequestOutcome::Accepted, 'item' => $item];
        });
    }

    /**
     * The song asked for. A custom request matching a requestable library song by normalised
     * artist and title becomes that song; one matching a hidden song stays custom (#142).
     *
     * @return array{canonical_track_id: int|null, custom_artist: string|null, custom_title: string|null}
     */
    protected function song(SongRequestData $request): array
    {
        if ($request->canonical_track_id !== null) {
            return ['canonical_track_id' => $request->canonical_track_id, 'custom_artist' => null, 'custom_title' => null];
        }

        $artist = trim((string) $request->custom_artist);
        $title = trim((string) $request->custom_title);

        $matchId = $title === '' ? null : CanonicalTrack::query()
            ->requestable()
            ->where('match_key', $this->normaliser->titleKey($title))
            ->whereIn('canonical_artist_id', CanonicalArtist::query()->where('match_key', $this->normaliser->artistKey($artist))->select('id'))
            ->value('id');

        return $matchId !== null
            ? ['canonical_track_id' => (int) $matchId, 'custom_artist' => null, 'custom_title' => null]
            : ['canonical_track_id' => null, 'custom_artist' => $artist, 'custom_title' => $title !== '' ? $title : null];
    }

    /**
     * A pending or accepted request for the same song
     *
     * @param  array{canonical_track_id: int|null, custom_artist: string|null, custom_title: string|null}  $song
     */
    protected function openDuplicate(SongList $list, array $song): ?SongListItem
    {
        return $this->sameSong(
            SongListItem::query()->where('song_list_id', $list->id)->whereIn('status', RequestStatus::open())->oldest('id'),
            $song,
        )->first();
    }

    /**
     * Whether the song is on one of the gig's setlists as do not play
     *
     * @param  array{canonical_track_id: int|null, custom_artist: string|null, custom_title: string|null}  $song
     */
    protected function isDoNotPlay(Gig $gig, array $song): bool
    {
        return $this->sameSong(
            SongListItem::query()
                ->where('type', SetlistSongType::DoNotPlay)
                ->whereIn('song_list_id', SongList::query()->setlists()->where('gig_id', $gig->id)->select('id')),
            $song,
        )->isNotEmpty();
    }

    /**
     * The items for the same song (see SongKeys)
     *
     * @param  Builder<SongListItem>  $items
     * @param  array{canonical_track_id: int|null, custom_artist: string|null, custom_title: string|null}  $song
     * @return Collection<int, SongListItem>
     */
    protected function sameSong(Builder $items, array $song): Collection
    {
        if ($song['canonical_track_id'] !== null) {
            return $items->where('canonical_track_id', $song['canonical_track_id'])->get()->toBase();
        }

        $key = $this->keys->key(null, $song['custom_artist'], $song['custom_title']);

        return $items->whereNull('canonical_track_id')->get()
            ->filter(fn (SongListItem $item) => $this->keys->forItem($item) === $key)
            ->values()
            ->toBase();
    }
}

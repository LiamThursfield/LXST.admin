<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Data\Dj\EffectiveRequestSettings;
use App\Enums\Dj\RequestOutcome;
use App\Enums\Dj\RequestRefusal;
use App\Enums\Dj\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Dj\GigRequestListRequest;
use App\Http\Resources\Tenant\Api\V1\Dj\PublicRequestResource;
use App\Models\Dj\Gig;
use App\Models\Dj\SongListItem;
use App\Models\Dj\SongListItemVote;
use App\Services\Cms\Api\VisitorPrivacy;
use App\Services\Dj\Api\DjApiCache;
use App\Services\Dj\Requests\Exceptions\RequestRefused;
use App\Services\Dj\Requests\RequestAccess;
use App\Services\Dj\Requests\RequestVotes;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A gig's public request list, so visitors upvote rather than ask again, when the gig allows it
 */
class GigRequestController extends Controller
{
    public function __construct(
        protected RequestAccess $access,
        protected DjApiCache $cache,
    ) {}

    /**
     * Pending and accepted requests, most votes first, and the latest played ones. Which the
     * visitor has voted for is added outside the cache.
     */
    public function index(GigRequestListRequest $request, Gig $gig): JsonResponse
    {
        $settings = $gig->requestSettings();

        if (! $settings->show_public_list) {
            throw new RequestRefused(RequestRefusal::ListHidden);
        }

        $this->access->ensureAllowed($gig, $this->access->pass($request, $gig));

        /** @var array{requests: list<array<string, mixed>>, played: list<array<string, mixed>>} $list */
        $list = $this->cache->rememberRequests($gig, 'list', fn (): array => $this->list($gig, $settings));

        $visitorHash = $request->visitorHash($gig->starts_at);
        $voted = $visitorHash === null ? [] : SongListItemVote::query()
            ->where('visitor_hash', $visitorHash)
            ->whereIn('song_list_item_id', array_column($list['requests'], 'id'))
            ->pluck('song_list_item_id')
            ->all();

        return response()->json([
            'data' => [
                'allow_upvotes' => $settings->allow_upvotes && $gig->requestsOpenAt(now()),
                'requests' => array_map(fn (array $item) => [...$item, 'voted' => in_array($item['id'], $voted, true)], $list['requests']),
                'played' => $list['played'],
            ],
        ]);
    }

    /**
     * One upvote per visitor. Bots get the same response, without a vote.
     */
    public function upvote(GigRequestListRequest $request, Gig $gig, SongListItem $item, RequestVotes $votes, VisitorPrivacy $privacy): JsonResponse
    {
        abort_unless($item->song_list_id === $gig->requestsList?->id, Response::HTTP_NOT_FOUND);

        $settings = $gig->requestSettings();

        if (! $gig->requestsOpenAt(now())) {
            throw new RequestRefused(RequestRefusal::Closed);
        }

        if (! $settings->allow_upvotes) {
            throw new RequestRefused(RequestRefusal::UpvotesOff);
        }

        $pass = $this->access->pass($request, $gig);
        $this->access->ensureAllowed($gig, $pass);

        if (! in_array($item->status, RequestStatus::open(), true)) {
            throw new RequestRefused(RequestRefusal::NotVotable);
        }

        if ($privacy->isBot($request->visitorUserAgent())) {
            $outcome = RequestOutcome::Voted;
        } else {
            $outcome = $votes->add($item, $request->visitorHash($gig->starts_at), $pass) ? RequestOutcome::Voted : RequestOutcome::AlreadyVoted;
        }

        return response()->json([
            'data' => [
                'outcome' => $outcome->value,
                'message' => $outcome->message(),
                'request' => ['id' => $item->id, 'votes' => $item->votes()->count()],
            ],
        ]);
    }

    /**
     * @return array{requests: list<array<string, mixed>>, played: list<array<string, mixed>>}
     */
    protected function list(Gig $gig, EffectiveRequestSettings $settings): array
    {
        $list = $gig->requestsList;

        if ($list === null) {
            return ['requests' => [], 'played' => []];
        }

        $items = fn (): Builder => SongListItem::query()
            ->where('song_list_id', $list->id)
            ->with(['song.artist', 'guestPass'])
            ->withCount('votes');
        /** @var Closure(SongListItem): array<string, mixed> $present */
        $present = fn (SongListItem $item): array => (new PublicRequestResource($item, $settings->show_pass_labels))->resolve();

        return [
            'requests' => array_values($items()
                ->whereIn('status', RequestStatus::open())
                ->orderByDesc('votes_count')
                ->oldest('id')
                ->get()
                ->map($present)
                ->all()),
            'played' => array_values($items()
                ->where('status', RequestStatus::Played)
                ->latest('played_at')
                ->limit((int) config('dj.requests.played_shown'))
                ->get()
                ->map($present)
                ->all()),
        ];
    }
}

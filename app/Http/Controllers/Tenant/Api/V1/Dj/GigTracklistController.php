<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Enums\Dj\RequestRefusal;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Api\V1\Dj\GigTracklistQuery;
use App\Http\Resources\Tenant\Api\V1\Dj\PublicTrackResource;
use App\Models\Dj\Gig;
use App\Services\Dj\Api\DjApiCache;
use App\Services\Dj\CurrentGig;
use App\Services\Dj\Requests\Exceptions\RequestRefused;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

/**
 * A gig's public tracklist: what's playing now, and what played before it, a page at a time
 */
class GigTracklistController extends Controller
{
    public function __construct(
        protected GigTracklistQuery $query,
        protected DjApiCache $cache,
    ) {}

    public function current(Request $request, CurrentGig $currentGig): JsonResponse
    {
        $gig = $currentGig->resolve();

        abort_if($gig === null, Response::HTTP_NOT_FOUND, __('dj.api.no_gig'));

        return $this->tracklist($request, $gig);
    }

    public function show(Request $request, Gig $gig): JsonResponse
    {
        return $this->tracklist($request, $gig);
    }

    protected function tracklist(Request $request, Gig $gig): JsonResponse
    {
        $settings = $gig->requestSettings();

        if (! $settings->show_tracklist) {
            throw new RequestRefused(RequestRefusal::TracklistHidden);
        }

        $page = (int) ($request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:1000']])['page'] ?? 1);

        return response()->json($this->cache->rememberForGig($gig, "tracklist|{$page}", function () use ($gig, $settings, $page): array {
            $tracks = $this->query->handle($gig, $settings, $page);
            $nowPlaying = $this->query->nowPlaying($gig, $settings);

            return [
                'data' => PublicTrackResource::collection($tracks->getCollection())->resolve(),
                'now_playing' => $nowPlaying === null ? null : (new PublicTrackResource($nowPlaying))->resolve(),
                'gig' => ['id' => $gig->public_id, 'name' => $gig->name],
                'meta' => $this->meta($tracks),
            ];
        }));
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $tracks
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    protected function meta(LengthAwarePaginator $tracks): array
    {
        return [
            'current_page' => $tracks->currentPage(),
            'last_page' => $tracks->lastPage(),
            'per_page' => $tracks->perPage(),
            'total' => $tracks->total(),
        ];
    }
}

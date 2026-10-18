<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Api\V1\Dj\TrackSearchQuery;
use App\Http\Requests\Tenant\Api\V1\Dj\TrackSearchRequest;
use App\Models\Dj\CanonicalTrack;
use App\Services\Dj\Api\DjApiCache;
use App\Services\Dj\CurrentGig;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Library search for requesting a song, while the gig's requests are open
 */
class TrackController extends Controller
{
    public function index(TrackSearchRequest $request, TrackSearchQuery $query, CurrentGig $currentGig, DjApiCache $cache): JsonResponse
    {
        $gig = $request->gig($currentGig);

        abort_if($gig === null, Response::HTTP_FORBIDDEN, __('dj.api.no_gig'));
        abort_unless($gig->requestsOpenAt(now()), Response::HTTP_FORBIDDEN, __('dj.api.requests_closed'));

        $search = $request->searchTerm();

        return response()->json($cache->remember('tracks|'.mb_strtolower($search), fn (): array => [
            'data' => $query->handle($search)->map(fn (CanonicalTrack $track) => [
                'id' => $track->id,
                'title' => $track->title,
                'artist' => $track->artist?->name,
                'duration' => $track->formattedDuration(),
            ])->values()->all(),
        ]));
    }
}

<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Dj\NowPlayingStoreRequest;
use App\Models\Dj\Gig;
use App\Services\Dj\CurrentGig;
use App\Services\Dj\NowPlaying\NowPlayingRecorder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tracks the Rekordbox CLI tool reports as they're played, added to the live gig (#124). See
 * docs/api/dj-now-playing.md for the contract.
 */
class NowPlayingController extends Controller
{
    /**
     * Without a gig on (or pinned as live), the report is refused: live history is for gigs, and the
     * next library import brings the plays in anyway
     */
    public function store(NowPlayingStoreRequest $request, CurrentGig $currentGig, NowPlayingRecorder $recorder): JsonResponse
    {
        $report = $request->report();
        $publicId = $request->gigPublicId();

        $gig = $publicId !== null
            ? Gig::query()->where('public_id', $publicId)->first()
            : $currentGig->resolve($report->played_at);

        if ($gig === null || ($publicId === null && ! $gig->is_pinned && ! $gig->isOnAt($report->played_at))) {
            return response()->json([
                'message' => __('dj.now_playing.no_gig'),
                'reason' => 'no_gig',
            ], Response::HTTP_CONFLICT);
        }

        $result = $recorder->record($gig, $report);
        $play = $result['play'];

        return response()->json([
            'data' => [
                'outcome' => $result['duplicate'] ? 'duplicate' : 'recorded',
                'gig' => ['id' => $gig->public_id, 'name' => $gig->name],
                'play' => [
                    'id' => $play->id,
                    'matched' => match (true) {
                        $play->library_track_id !== null => 'library_track',
                        $play->canonical_track_id !== null => 'song',
                        default => 'none',
                    },
                    'played_at' => $play->played_at?->toIso8601String(),
                ],
                'requests_played' => $result['requests_played'],
            ],
        ], $result['duplicate'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}

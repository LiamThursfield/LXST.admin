<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Dj\GuestSetlistResource;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\SongList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * A setlist for a guest with a pass, and the songs they can add to it
 */
class GuestSetlistController extends Controller
{
    public function show(SongList $songList): GuestSetlistResource
    {
        abort_unless($songList->isSetlist(), Response::HTTP_NOT_FOUND);

        $songList->load(['gig', 'items.song.artist', 'items.guestPass']);

        return new GuestSetlistResource($songList);
    }

    /**
     * Library songs to add, leaving out what's hidden from requests
     */
    public function songs(Request $request, SongList $songList): JsonResponse
    {
        abort_unless($songList->isSetlist(), Response::HTTP_NOT_FOUND);

        $search = trim((string) ($request->validate(['search' => ['nullable', Rule::string()->max(255)]])['search'] ?? ''));

        $songs = $search === '' ? collect() : CanonicalTrack::query()
            ->requestable()
            ->with('artist')
            ->where(fn (Builder $query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereHas('artist', fn (Builder $artist) => $artist->whereLike('name', "%{$search}%")))
            ->orderBy('title')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $songs->map(fn (CanonicalTrack $song) => [
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist?->name,
            ])->values(),
        ]);
    }
}

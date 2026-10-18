<?php

namespace App\Http\Controllers\Tenant\Api\V1\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Dj\GuestSetlistItemRequest;
use App\Http\Resources\Tenant\Api\V1\Dj\GuestSetlistItemResource;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Dj\SongLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guest adding songs to a setlist, and changing or removing the ones their pass added
 */
class GuestSetlistItemController extends Controller
{
    public function store(GuestSetlistItemRequest $request, SongList $songList, SongLists $songLists): JsonResponse
    {
        abort_unless($songList->isSetlist(), Response::HTTP_NOT_FOUND);

        $item = $songLists->add($songList, $request->itemAttributes());
        $item->load(['song.artist', 'guestPass']);

        return (new GuestSetlistItemResource($item))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(GuestSetlistItemRequest $request, SongList $songList, SongListItem $item): GuestSetlistItemResource
    {
        $this->ensureOnList($songList, $item);

        $item->update($request->validated());
        $item->load(['song.artist', 'guestPass']);

        return new GuestSetlistItemResource($item);
    }

    public function destroy(Request $request, SongList $songList, SongListItem $item): HttpResponse
    {
        $this->ensureOnList($songList, $item);

        /** @var GuestPass $pass */
        $pass = $request->attributes->get('guest_pass');
        abort_unless($item->guest_pass_id === $pass->id, Response::HTTP_FORBIDDEN);

        $item->delete();

        return response()->noContent();
    }

    protected function ensureOnList(SongList $songList, SongListItem $item): void
    {
        abort_unless($songList->isSetlist() && $item->song_list_id === $songList->id, Response::HTTP_NOT_FOUND);
    }
}

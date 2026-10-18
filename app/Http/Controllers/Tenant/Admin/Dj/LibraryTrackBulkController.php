<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\LibraryTrackBulkRequest;
use App\Models\Dj\CanonicalTrack;
use App\Models\User;
use App\Services\Dj\Matching\CanonicalAssigner;
use Illuminate\Http\RedirectResponse;

/**
 * Bulk actions on library tracks selected on a page
 */
class LibraryTrackBulkController extends Controller
{
    /**
     * Links the selected tracks to one song, an existing one or a new one
     */
    public function assign(LibraryTrackBulkRequest $request, CanonicalAssigner $assigner): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tracks = $request->tracks();
        $songId = $request->validated('song_id');

        $song = $songId !== null
            ? $assigner->assignTracks($tracks, CanonicalTrack::query()->whereKey((int) $songId)->firstOrFail(), $user)
            : $assigner->assignTracksToNewSong($tracks, $request->validated('new_song_title'), $user);

        return redirect()->back()->with('success', trans_choice('dj.bulk.flash.tracks_assigned', $tracks->count(), [
            'count' => $tracks->count(),
            'song' => $song->title,
        ]));
    }

    /**
     * Verifies (or unverifies) the selected tracks' songs
     */
    public function verify(LibraryTrackBulkRequest $request): RedirectResponse
    {
        $verified = $request->boolean('verified');

        $count = CanonicalTrack::query()
            ->whereKey($request->tracks()->pluck('canonical_track_id')->filter()->unique()->all())
            ->update(['verified_at' => $verified ? now() : null]);

        return redirect()->back()->with('success', trans_choice($verified ? 'dj.bulk.flash.songs_verified' : 'dj.bulk.flash.songs_unverified', $count, ['count' => $count]));
    }
}

<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\LibraryArtistBulkRequest;
use App\Models\Dj\CanonicalArtist;
use App\Models\User;
use App\Services\Dj\Matching\CanonicalAssigner;
use Illuminate\Http\RedirectResponse;

/**
 * Bulk actions on library artists selected on a page
 */
class LibraryArtistBulkController extends Controller
{
    /**
     * Links the selected library artists to one canonical artist, an existing one or a new one
     */
    public function assign(LibraryArtistBulkRequest $request, CanonicalAssigner $assigner): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $artists = $request->artists();
        $artistId = $request->validated('canonical_artist_id');

        $artist = $artistId !== null
            ? $assigner->assignArtists($artists, CanonicalArtist::query()->whereKey((int) $artistId)->firstOrFail(), $user)
            : $assigner->assignArtistsToNewArtist($artists, $request->validated('new_artist_name'), $user);

        return redirect()->back()->with('success', trans_choice('dj.bulk.flash.artists_assigned', $artists->count(), [
            'count' => $artists->count(),
            'artist' => $artist->name,
        ]));
    }

    /**
     * Verifies (or unverifies) the selected library artists' canonical artists
     */
    public function verify(LibraryArtistBulkRequest $request): RedirectResponse
    {
        $verified = $request->boolean('verified');

        $count = CanonicalArtist::query()
            ->whereKey($request->artists()->pluck('canonical_artist_id')->filter()->unique()->all())
            ->update(['verified_at' => $verified ? now() : null]);

        return redirect()->back()->with('success', trans_choice($verified ? 'dj.bulk.flash.artists_verified' : 'dj.bulk.flash.artists_unverified', $count, ['count' => $count]));
    }

    /**
     * Hides (or shows) the selected artists' canonical artists, with their songs, in song request search
     */
    public function hide(LibraryArtistBulkRequest $request): RedirectResponse
    {
        $hidden = $request->boolean('hidden');

        $count = CanonicalArtist::query()
            ->whereKey($request->artists()->pluck('canonical_artist_id')->filter()->unique()->all())
            ->update(['hidden_from_requests_at' => $hidden ? now() : null]);

        return redirect()->back()->with('success', trans_choice($hidden ? 'dj.bulk.flash.artists_hidden' : 'dj.bulk.flash.artists_shown', $count, ['count' => $count]));
    }
}

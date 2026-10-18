<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Admin\Dj\CanonicalMergeResource;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryTrack;
use App\Services\Dj\Matching\CanonicalRematcher;
use App\Services\Dj\Matching\MatchSuggestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reviewing canonical matching: near matches to merge or dismiss, unmatched tracks, and a log of
 * every merge and split
 */
class MatchingController extends Controller
{
    public function index(Request $request, MatchSuggestions $suggestions): Response
    {
        Gate::authorize('viewAny', CanonicalTrack::class);

        return Inertia::render('admin/dj/matching/Index', [
            // Comparing every record takes a moment, so the page loads first
            'trackSuggestions' => Inertia::defer(fn () => $suggestions->tracks()->map(fn (array $pair) => [
                'similarity' => $pair['similarity'],
                'first' => $this->trackSummary($pair['first']),
                'second' => $this->trackSummary($pair['second']),
            ])),
            'artistSuggestions' => Inertia::defer(fn () => $suggestions->artists()->map(fn (array $pair) => [
                'similarity' => $pair['similarity'],
                'first' => $this->artistSummary($pair['first']),
                'second' => $this->artistSummary($pair['second']),
            ])),
            // Records imported before matching keys existed need a re-match before they can be compared
            'needsRematch' => CanonicalTrack::query()->whereNull('match_key')->exists()
                || CanonicalArtist::query()->whereNull('match_key')->exists(),
            'unmatchedCount' => LibraryTrack::query()->whereNull('canonical_track_id')->count(),
            'merges' => CanonicalMergeResource::collection(
                CanonicalMerge::query()->with('user')->latest('id')->paginate(15, pageName: 'merges_page')
            ),
            'canManage' => $request->user()?->can('update', CanonicalTrack::class) ?? false,
        ]);
    }

    /**
     * Runs the automatic rules over everything already imported
     */
    public function rematch(CanonicalRematcher $rematcher): RedirectResponse
    {
        Gate::authorize('update', CanonicalTrack::class);

        $counts = $rematcher->run();

        return redirect()->route('admin.dj.matching.index')
            ->with('success', __('dj.matching.flash.rematched', [
                'artists' => $counts['artists_merged'],
                'tracks' => $counts['tracks_merged'],
                'linked' => $counts['tracks_linked'],
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function trackSummary(CanonicalTrack $track): array
    {
        return [
            'id' => $track->id,
            'name' => $track->title,
            'detail' => $track->artist?->name,
            'sources_count' => (int) $track->getAttribute('library_tracks_count'),
            'is_verified' => $track->isVerified(),
            'url' => route('admin.dj.tracks.index', ['filter' => ['search' => $track->title]]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function artistSummary(CanonicalArtist $artist): array
    {
        return [
            'id' => $artist->id,
            'name' => $artist->name,
            'detail' => null,
            'sources_count' => (int) $artist->getAttribute('library_artists_count'),
            'is_verified' => $artist->isVerified(),
            'url' => route('admin.dj.artists.index', ['filter' => ['search' => $artist->name]]),
        ];
    }
}

<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CanonicalTrackController extends Controller
{
    /**
     * Canonical tracks matching a title or artist, for picking one to merge into
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CanonicalTrack::class);

        $validated = $request->validate([
            'search' => ['nullable', Rule::string()->max(255)],
            'except' => ['nullable', 'integer'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $tracks = CanonicalTrack::query()
            ->with('artist')
            ->withCount('libraryTracks')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereHas('artist', fn (Builder $artist) => $artist->whereLike('name', "%{$search}%"))
            ))
            ->when(isset($validated['except']), fn (Builder $query) => $query->whereKeyNot($validated['except']))
            ->orderBy('title')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $tracks->map(fn (CanonicalTrack $track) => [
                'id' => $track->id,
                'title' => $track->title,
                'artist' => $track->artist?->name,
                'sources_count' => $track->library_tracks_count,
            ])->values(),
        ]);
    }

    /**
     * Marks the canonical track as checked, so matching never changes it, or undoes that
     */
    public function verify(Request $request, CanonicalTrack $canonicalTrack): RedirectResponse
    {
        Gate::authorize('update', $canonicalTrack);

        $verified = $request->validate(['verified' => ['required', 'boolean']])['verified'];
        $canonicalTrack->update(['verified_at' => $verified ? now() : null]);

        return redirect()->back()->with('success', __($verified ? 'dj.matching.flash.verified' : 'dj.matching.flash.unverified'));
    }
}

<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\CanonicalArtistIndexQuery;
use App\Http\Requests\Tenant\Admin\Dj\CanonicalArtistIndexRequest;
use App\Http\Requests\Tenant\Admin\Dj\CanonicalArtistUpdateRequest;
use App\Http\Resources\Tenant\Admin\Dj\CanonicalArtistResource;
use App\Http\Resources\Tenant\Admin\Dj\CanonicalMergeResource;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryArtist;
use App\Services\Dj\Matching\TrackNormaliser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Artists: the canonical artists that count once across libraries
 */
class CanonicalArtistController extends Controller
{
    public function index(CanonicalArtistIndexRequest $request, CanonicalArtistIndexQuery $query): Response
    {
        /** @var array<string, mixed> $filter */
        $filter = $request->validated('filter') ?? [];

        return Inertia::render('admin/dj/canonical-artist/Index', [
            'filter' => array_filter($filter, fn (mixed $value) => $value !== null && $value !== ''),
            'sort' => $request->validated('sort') ?? 'name',
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('dj.canonical_artists.columns.name')],
                ['accessorKey' => 'tracks_count', 'header' => __('dj.canonical_artists.columns.songs')],
                ['accessorKey' => 'library_artists_count', 'header' => __('dj.canonical_artists.columns.sources')],
            ],
            'artists' => CanonicalArtistResource::collection($query->handle($request)),
        ]);
    }

    public function show(Request $request, CanonicalArtist $canonicalArtist): Response
    {
        Gate::authorize('viewAny', CanonicalArtist::class);

        $canonicalArtist->load('libraryArtists.library');

        return Inertia::render('admin/dj/canonical-artist/Show', [
            'artist' => [
                'id' => $canonicalArtist->id,
                'name' => $canonicalArtist->name,
                'is_verified' => $canonicalArtist->isVerified(),
                'is_hidden_from_requests' => $canonicalArtist->isHiddenFromRequests(),
                'songs_url' => route('admin.dj.songs.index', ['filter' => ['artist_id' => $canonicalArtist->id]]),
            ],
            'songs' => $canonicalArtist->tracks()->withCount('libraryTracks')->orderBy('title')->limit(100)->get()
                ->map(fn (CanonicalTrack $song) => [
                    'id' => $song->id,
                    'title' => $song->title,
                    'library_tracks_count' => $song->library_tracks_count,
                    'is_verified' => $song->isVerified(),
                    'url' => route('admin.dj.songs.show', $song),
                ]),
            'songsCount' => $canonicalArtist->tracks()->count(),
            'libraryArtists' => $canonicalArtist->libraryArtists
                ->map(fn (LibraryArtist $artist) => [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'library' => $artist->library->name,
                    'tracks_url' => route('admin.dj.tracks.index', ['filter' => ['artist_id' => $artist->id]]),
                ])->values(),
            'merges' => CanonicalMergeResource::collection(
                CanonicalMerge::query()
                    ->with('user')
                    ->where('record', CanonicalRecord::Artist)
                    ->where(fn (Builder $query) => $query->where('target_id', $canonicalArtist->id)->orWhere('source_id', $canonicalArtist->id))
                    ->latest('id')
                    ->limit(20)
                    ->get()
            ),
            'canManage' => $request->user()?->can('update', $canonicalArtist) ?? false,
        ]);
    }

    /**
     * Renames the artist, and verifies it so matching never changes it back
     */
    public function update(CanonicalArtistUpdateRequest $request, CanonicalArtist $canonicalArtist, TrackNormaliser $normaliser): RedirectResponse
    {
        /** @var string $name */
        $name = $request->validated('name');

        $canonicalArtist->update([
            'name' => $name,
            'match_key' => $normaliser->artistKey($name),
            'verified_at' => $canonicalArtist->verified_at ?? now(),
        ]);

        return redirect()->route('admin.dj.canonical-artists.show', $canonicalArtist)
            ->with('success', __('dj.canonical_artists.flash.updated'));
    }

    /**
     * Marks the artist as checked, so matching never changes it, or undoes that
     */
    public function verify(Request $request, CanonicalArtist $canonicalArtist): RedirectResponse
    {
        Gate::authorize('update', $canonicalArtist);

        $verified = $request->validate(['verified' => ['required', 'boolean']])['verified'];
        $canonicalArtist->update(['verified_at' => $verified ? now() : null]);

        return redirect()->back()->with('success', __($verified ? 'dj.matching.flash.verified' : 'dj.matching.flash.unverified'));
    }

    /**
     * Hides it from song request search, or shows it again
     */
    public function hide(Request $request, CanonicalArtist $canonicalArtist): RedirectResponse
    {
        Gate::authorize('update', $canonicalArtist);

        $hidden = $request->validate(['hidden' => ['required', 'boolean']])['hidden'];
        $canonicalArtist->update(['hidden_from_requests_at' => $hidden ? now() : null]);

        return redirect()->back()->with('success', __($hidden ? 'dj.requests_visibility.flash.artist_hidden' : 'dj.requests_visibility.flash.artist_shown'));
    }

    /**
     * Canonical artists matching a name, for picking a song's artist or one to merge into
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CanonicalArtist::class);

        $validated = $request->validate([
            'search' => ['nullable', Rule::string()->max(255)],
            'except' => ['nullable', 'integer'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $artists = CanonicalArtist::query()
            ->withCount('tracks')
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', "%{$search}%"))
            ->when(isset($validated['except']), fn (Builder $query) => $query->whereKeyNot($validated['except']))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $artists->map(fn (CanonicalArtist $artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'tracks_count' => $artist->tracks_count,
            ])->values(),
        ]);
    }
}

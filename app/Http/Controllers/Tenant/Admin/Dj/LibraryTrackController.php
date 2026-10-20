<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\MusicalKey;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\LibraryTrackIndexQuery;
use App\Http\Requests\Tenant\Admin\Dj\LibraryTrackIndexRequest;
use App\Http\Resources\Tenant\Admin\Dj\LibraryTrackDetailResource;
use App\Http\Resources\Tenant\Admin\Dj\LibraryTrackResource;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browsing the library's tracks. Tracks come from imports, so they're never edited here.
 */
class LibraryTrackController extends Controller
{
    public function index(LibraryTrackIndexRequest $request, LibraryTrackIndexQuery $query): Response
    {
        /** @var array<string, mixed> $filter */
        $filter = $request->validated('filter') ?? [];
        $artistId = $filter['artist_id'] ?? null;

        return Inertia::render('admin/dj/track/Index', [
            'filter' => array_filter($filter, fn (mixed $value) => $value !== null && $value !== ''),
            'sort' => $request->validated('sort') ?? 'title',
            'columns' => [
                ['accessorKey' => 'title', 'header' => __('dj.tracks.columns.title')],
                ['accessorKey' => 'bpm', 'header' => __('dj.tracks.columns.bpm')],
                ['accessorKey' => 'key', 'header' => __('dj.tracks.columns.key')],
                ['accessorKey' => 'duration', 'header' => __('dj.tracks.columns.duration')],
                ['accessorKey' => 'genre', 'header' => __('dj.tracks.columns.genre')],
                ['accessorKey' => 'library', 'header' => __('dj.tracks.columns.library')],
            ],
            'tracks' => LibraryTrackResource::collection($query->handle($request)),
            'libraries' => Library::query()->orderBy('name')->get()
                ->map(fn (Library $library) => ['value' => (string) $library->id, 'label' => $library->name])
                ->values(),
            'genres' => LibraryTrack::query()->present()->whereNotNull('genre')
                ->distinct()->orderBy('genre')->pluck('genre')
                ->map(fn (string $genre) => ['value' => $genre, 'label' => $genre])
                ->values(),
            // Around the Camelot wheel, minor then major for each number
            'keys' => collect(MusicalKey::cases())
                ->sortBy(fn (MusicalKey $key) => [$key->number(), $key->isMinor() ? 0 : 1])
                ->map(fn (MusicalKey $key) => $key->toSelectItem())
                ->values(),
            // Named, so the artist filter can be shown and cleared
            'artist' => $artistId === null ? null : LibraryArtist::query()->whereKey($artistId)->value('name'),
            'canManageMatching' => $request->user()?->can('update', CanonicalTrack::class) ?? false,
        ]);
    }

    public function show(Request $request, LibraryTrack $track): Response
    {
        Gate::authorize('view', $track);

        $track->load([
            'artist',
            'library',
            'tags',
            'canonicalTrack.artist',
            'canonicalTrack.libraryTracks.library',
            'historyPlays' => fn ($plays) => $plays->counted()->with('session')->limit(50),
        ])->loadCount(['historyPlays' => fn ($plays) => $plays->counted()]);

        return Inertia::render('admin/dj/track/Show', [
            'track' => new LibraryTrackDetailResource($track),
            'canManageMatching' => $request->user()?->can('update', CanonicalTrack::class) ?? false,
        ]);
    }
}

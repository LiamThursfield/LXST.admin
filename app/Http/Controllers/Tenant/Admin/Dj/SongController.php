<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\MusicalKey;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\SongIndexQuery;
use App\Http\Requests\Tenant\Admin\Dj\SongIndexRequest;
use App\Http\Requests\Tenant\Admin\Dj\SongUpdateRequest;
use App\Http\Resources\Tenant\Admin\Dj\CanonicalMergeResource;
use App\Http\Resources\Tenant\Admin\Dj\LibraryTrackResource;
use App\Http\Resources\Tenant\Admin\Dj\SongResource;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\SongList;
use App\Services\Dj\Matching\CanonicalCleaner;
use App\Services\Dj\Matching\TrackNormaliser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Songs: the canonical tracks that count once across libraries and re-imports
 */
class SongController extends Controller
{
    public function index(SongIndexRequest $request, SongIndexQuery $query): Response
    {
        /** @var array<string, mixed> $filter */
        $filter = $request->validated('filter') ?? [];
        $artistId = $filter['artist_id'] ?? null;

        return Inertia::render('admin/dj/song/Index', [
            'filter' => array_filter($filter, fn (mixed $value) => $value !== null && $value !== ''),
            'sort' => $request->validated('sort') ?? 'title',
            'columns' => [
                ['accessorKey' => 'title', 'header' => __('dj.songs.columns.title')],
                ['accessorKey' => 'bpm', 'header' => __('dj.songs.columns.bpm')],
                ['accessorKey' => 'key', 'header' => __('dj.songs.columns.key')],
                ['accessorKey' => 'duration', 'header' => __('dj.songs.columns.duration')],
                ['accessorKey' => 'library_tracks_count', 'header' => __('dj.songs.columns.sources')],
            ],
            'songs' => SongResource::collection($query->handle($request)),
            'keys' => $this->keyItems(),
            'artist' => $artistId === null ? null : CanonicalArtist::query()->whereKey($artistId)->value('name'),
            'orphanedCount' => CanonicalCleaner::orphanedTracks(CanonicalTrack::query())->count(),
            'canManage' => $request->user()?->can('update', CanonicalTrack::class) ?? false,
        ]);
    }

    public function show(Request $request, CanonicalTrack $song): Response
    {
        Gate::authorize('viewAny', CanonicalTrack::class);

        $song->load(['artist', 'libraryTracks' => fn ($tracks) => $tracks->with(['library', 'artist'])->orderBy('title')]);
        $trackIds = $song->libraryTracks->modelKeys();

        $plays = HistoryPlay::query()->counted()->whereIn('library_track_id', $trackIds);

        return Inertia::render('admin/dj/song/Show', [
            'song' => [
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist === null ? null : [
                    'id' => $song->artist->id,
                    'name' => $song->artist->name,
                    'url' => route('admin.dj.canonical-artists.show', $song->artist),
                    'is_hidden_from_requests' => $song->artist->isHiddenFromRequests(),
                ],
                'bpm' => $song->bpm === null ? null : (float) $song->bpm,
                'duration' => $song->formattedDuration(),
                'key' => LibraryTrackResource::key($song->key),
                'is_verified' => $song->isVerified(),
                'is_hidden_from_requests' => $song->isHiddenFromRequests(),
                // Whether visitors can find it in song request search, after every hiding rule
                'is_requestable' => CanonicalTrack::query()->requestable()->whereKey($song->id)->exists(),
            ],
            'libraryTracks' => LibraryTrackResource::collection($song->libraryTracks),
            'playsCount' => (clone $plays)->count(),
            'plays' => (clone $plays)->with(['session', 'track.library'])->latest('played_at')->limit(50)->get()
                ->map(fn (HistoryPlay $play) => [
                    'session' => $play->session->name,
                    'library' => $play->track?->library->name,
                    'played_at' => $play->played_at?->toIso8601String(),
                ]),
            'merges' => CanonicalMergeResource::collection(
                CanonicalMerge::query()
                    ->with('user')
                    ->where('record', CanonicalRecord::Track)
                    ->where(fn (Builder $query) => $query->where('target_id', $song->id)->orWhere('source_id', $song->id))
                    ->latest('id')
                    ->limit(20)
                    ->get()
            ),
            'setlists' => SongList::query()->setlists()
                ->whereHas('items', fn (Builder $items) => $items->where('canonical_track_id', $song->id))
                ->with(['gig', 'items' => fn ($items) => $items->where('canonical_track_id', $song->id)])
                ->orderBy('name')
                ->get()
                ->map(fn (SongList $setlist) => [
                    'id' => $setlist->id,
                    'name' => $setlist->name,
                    'gig' => $setlist->gig?->name,
                    'type' => $setlist->items->first()?->type?->label(),
                    'url' => route('admin.dj.setlists.show', $setlist),
                ]),
            'canManage' => $request->user()?->can('update', $song) ?? false,
        ]);
    }

    /**
     * Corrects the song's details, and verifies it so matching never changes them back
     */
    public function edit(CanonicalTrack $song): Response
    {
        Gate::authorize('update', $song);

        $song->load('artist');

        return Inertia::render('admin/dj/song/Edit', [
            'song' => [
                'id' => $song->id,
                'title' => $song->title,
                'artist' => $song->artist === null ? null : [
                    'id' => $song->artist->id,
                    'name' => $song->artist->name,
                ],
                'bpm' => $song->bpm === null ? null : (float) $song->bpm,
                'duration' => $song->formattedDuration(),
                'key' => LibraryTrackResource::key($song->key),
                'is_verified' => $song->isVerified(),
            ],
            'keys' => $this->keyItems(),
        ]);
    }

    public function update(SongUpdateRequest $request, CanonicalTrack $song, TrackNormaliser $normaliser): RedirectResponse
    {
        $attributes = $request->songAttributes();

        $song->update([
            ...$attributes,
            'match_key' => $normaliser->titleKey((string) $attributes['title']),
            'verified_at' => $song->verified_at ?? now(),
        ]);

        return redirect()->route('admin.dj.songs.show', $song)
            ->with('success', __('dj.songs.flash.updated'));
    }

    /**
     * Deletes songs and artists nothing links to any more, keeping verified ones
     */
    public function cleanup(CanonicalCleaner $cleaner): RedirectResponse
    {
        Gate::authorize('update', CanonicalTrack::class);

        $counts = $cleaner->run();

        return redirect()->back()->with('success', __('dj.songs.flash.cleaned', $counts));
    }

    /**
     * Around the Camelot wheel, minor then major for each number
     *
     * @return list<array{value: string, label: string}>
     */
    protected function keyItems(): array
    {
        return array_values(collect(MusicalKey::cases())
            ->sortBy(fn (MusicalKey $key) => [$key->number(), $key->isMinor() ? 0 : 1])
            ->map(fn (MusicalKey $key) => $key->toSelectItem())
            ->all());
    }
}

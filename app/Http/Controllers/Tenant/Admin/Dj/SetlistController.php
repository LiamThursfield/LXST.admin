<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Enums\Dj\SongListKind;
use App\Enums\Dj\SongListVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\SetlistStoreRequest;
use App\Http\Requests\Tenant\Admin\Dj\SetlistUpdateRequest;
use App\Http\Resources\Tenant\Admin\Dj\SetlistItemResource;
use App\Http\Resources\Tenant\Admin\Dj\SetlistResource;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use App\Models\User;
use App\Services\Dj\SongLists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Setlists: planned lists of songs, e.g. a wedding's must plays and do not plays. A gig's
 * requests share the same model, but are handled by the request queue (#122).
 */
class SetlistController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SongList::class);

        $validated = $request->validate([
            'filter' => ['nullable', 'array'],
            'filter.search' => ['nullable', Rule::string()->max(255)],
            'filter.gig_id' => ['nullable', 'integer'],
        ]);
        $search = trim((string) ($validated['filter']['search'] ?? ''));
        $gigId = $validated['filter']['gig_id'] ?? null;

        $setlists = $this->withTypeCounts(SongList::query()->setlists()->with('gig'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereHas('gig', fn (Builder $gig) => $gig->whereLike('name', "%{$search}%"))))
            ->when($gigId !== null, fn (Builder $query) => $query->where('gig_id', $gigId))
            ->latest('updated_at')
            ->paginate(25)
            ->appends($request->query());

        return Inertia::render('admin/dj/setlist/Index', [
            'filter' => array_filter(['search' => $search, 'gig_id' => $gigId]),
            'setlists' => SetlistResource::collection($setlists),
            'gigs' => $this->gigOptions(),
            'visibilities' => SongListVisibility::asSelectItems(),
            'canCreate' => $request->user()?->can('create', SongList::class) ?? false,
        ]);
    }

    public function store(SetlistStoreRequest $request): RedirectResponse
    {
        $setlist = SongList::query()->create([...$request->validated(), 'kind' => SongListKind::Setlist]);

        return redirect()->route('admin.dj.setlists.show', $setlist)->with('success', __('dj.setlists.flash.created'));
    }

    public function show(Request $request, SongList $setlist): Response
    {
        $this->ensureSetlist($setlist);
        Gate::authorize('view', $setlist);

        $setlist->load(['gig', 'items.song.artist', 'items.addedBy']);

        return Inertia::render('admin/dj/setlist/Show', [
            'setlist' => new SetlistResource($this->withTypeCounts(SongList::query())->with('gig')->findOrFail($setlist->id)),
            'items' => SetlistItemResource::collection($setlist->items),
            'gigs' => $this->gigOptions(),
            'visibilities' => SongListVisibility::asSelectItems(),
            'types' => SetlistSongType::asSelectItems(),
            'canManage' => $request->user()?->can('update', $setlist) ?? false,
        ]);
    }

    public function update(SetlistUpdateRequest $request, SongList $setlist): RedirectResponse
    {
        $this->ensureSetlist($setlist);

        $setlist->update($request->validated());

        return redirect()->back()->with('success', __('dj.setlists.flash.updated'));
    }

    public function destroy(SongList $setlist): RedirectResponse
    {
        $this->ensureSetlist($setlist);
        Gate::authorize('delete', $setlist);

        $setlist->delete();

        return redirect()->route('admin.dj.setlists.index')->with('success', __('dj.setlists.flash.deleted'));
    }

    /**
     * Copies the setlist's songs, types and notes into a new setlist, e.g. for a repeat booking
     */
    public function duplicate(Request $request, SongList $setlist, SongLists $songLists): RedirectResponse
    {
        $this->ensureSetlist($setlist);
        Gate::authorize('create', SongList::class);

        $validated = $request->validate([
            'name' => ['required', Rule::string()->max(255)],
            'gig_id' => ['nullable', 'integer', Rule::exists(Gig::class, 'id')],
        ], [], ['name' => __('dj.setlists.fields.name'), 'gig_id' => __('dj.setlists.fields.gig')]);

        /** @var User $user */
        $user = $request->user();
        $gig = isset($validated['gig_id']) ? Gig::query()->whereKey((int) $validated['gig_id'])->first() : null;

        $copy = $songLists->duplicate($setlist->load('items'), $validated['name'], $gig, $user);

        return redirect()->route('admin.dj.setlists.show', $copy)->with('success', __('dj.setlists.flash.duplicated'));
    }

    /**
     * The request queue (#122) handles a gig's requests list, not these pages
     */
    protected function ensureSetlist(SongList $list): void
    {
        abort_unless($list->kind === SongListKind::Setlist, HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * @param  Builder<SongList>  $query
     * @return Builder<SongList>
     */
    protected function withTypeCounts(Builder $query): Builder
    {
        return $query->withCount(collect(SetlistSongType::cases())->mapWithKeys(fn (SetlistSongType $type) => [
            "items as {$type->value}_count" => fn (Builder $items) => $items->where('type', $type),
        ])->all());
    }

    /**
     * Gigs to pick from, the most recent first
     *
     * @return list<array{value: int, label: string}>
     */
    protected function gigOptions(): array
    {
        return array_values(Gig::query()->latest('starts_at')->limit(200)->get()
            ->map(fn (Gig $gig) => [
                'value' => $gig->id,
                'label' => $gig->name.' · '.$gig->starts_at->setTimezone($gig->timezone)->format('j M Y'),
            ])
            ->all());
    }
}

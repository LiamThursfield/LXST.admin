<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\RequestsMode;
use App\Enums\Dj\SongListKind;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\GigIndexQuery;
use App\Http\Requests\Tenant\Admin\Dj\GigIndexRequest;
use App\Http\Requests\Tenant\Admin\Dj\GigStoreRequest;
use App\Http\Requests\Tenant\Admin\Dj\GigUpdateRequest;
use App\Http\Resources\Tenant\Admin\Dj\GigResource;
use App\Http\Resources\Tenant\Admin\Dj\GuestPassResource;
use App\Http\Resources\Tenant\Admin\Dj\HistorySessionResource;
use App\Http\Resources\Tenant\Admin\Dj\PlayedTrackResource;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\SongList;
use App\Models\Settings\DjSettings;
use App\Services\Dj\CurrentGig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GigController extends Controller
{
    public function index(GigIndexRequest $request, GigIndexQuery $query, CurrentGig $currentGig): Response
    {
        $current = $currentGig->resolve();
        // On the app's request, which the resources are given, rather than this form request's copy
        request()->attributes->set(GigResource::CURRENT_GIG_ATTRIBUTE, $current?->id);

        return Inertia::render('admin/dj/gig/Index', [
            'filter' => array_filter(['search' => $request->validated('filter.search'), 'when' => $request->period()]),
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('dj.gigs.columns.name')],
                ['accessorKey' => 'starts_at', 'header' => __('dj.gigs.columns.when')],
                ['accessorKey' => 'requests_open', 'header' => __('dj.gigs.columns.requests')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'current' => $current === null ? null : new GigResource($current),
            'gigs' => GigResource::collection($query->handle($request)),
            'canCreate' => $request->user()?->can('create', Gig::class) ?? false,
        ]);
    }

    public function create(DjSettings $settings): Response
    {
        Gate::authorize('create', Gig::class);

        return Inertia::render('admin/dj/gig/Create', [
            'defaultTimezone' => $settings->default_timezone,
            'defaults' => $this->defaults($settings),
            'requestsModes' => RequestsMode::asSelectItems(),
        ]);
    }

    public function store(GigStoreRequest $request): RedirectResponse
    {
        $gig = Gig::query()->create($request->gigAttributes());

        return redirect()->route('admin.dj.gigs.edit', $gig)
            ->with('success', __('dj.gigs.flash.created'));
    }

    public function edit(Request $request, Gig $gig, DjSettings $settings, CurrentGig $currentGig): Response
    {
        Gate::authorize('update', $gig);

        $request->attributes->set(GigResource::CURRENT_GIG_ATTRIBUTE, $currentGig->resolve()?->id);

        return Inertia::render('admin/dj/gig/Edit', [
            'gig' => new GigResource($gig),
            // Gigs can overlap (e.g. two sets at a festival), but it's worth knowing
            'overlaps' => Gig::query()->overlapping($gig->starts_at, $gig->ends_at, $gig->id)->oldest('starts_at')->get()
                ->map(fn (Gig $other) => [
                    'id' => $other->id,
                    'name' => $other->name,
                    'url' => route('admin.dj.gigs.edit', $other),
                ]),
            'defaults' => $this->defaults($settings),
            'requestsModes' => RequestsMode::asSelectItems(),
            'setlists' => SongList::query()->setlists()->where('gig_id', $gig->id)->withCount('items')->orderBy('name')->get()
                ->map(fn (SongList $setlist) => [
                    'id' => $setlist->id,
                    'name' => $setlist->name,
                    'items_count' => $setlist->items_count,
                    'url' => route('admin.dj.setlists.show', $setlist),
                ]),
            'canCreateSetlist' => $request->user()?->can('create', SongList::class) ?? false,
            // Passes a guest needs to submit requests; with none, requests are open to anyone
            'requestPasses' => fn () => $request->user()?->can('create', [GuestPass::class, new SongList(['kind' => SongListKind::Requests])])
                ? GuestPassResource::collection($gig->requestsList?->guestPasses()->withCount('items')->oldest()->get() ?? [])
                : null,
            // What was played: the plays of the sessions linked to this gig, in time order
            'played' => PlayedTrackResource::collection(
                HistoryPlay::query()
                    ->whereHas('session', fn ($sessions) => $sessions->where('gig_id', $gig->id))
                    ->with(['session', 'song.artist', 'track.artist', 'track.canonicalTrack'])
                    ->orderBy('played_at')
                    ->orderBy('position')
                    ->limit(500)
                    ->get()
            ),
            'linkedSessions' => HistorySessionResource::collection(
                $gig->historySessions()->with(['gig', 'library'])->withCount('plays')->orderBy('started_at')->get()
            ),
        ]);
    }

    public function update(GigUpdateRequest $request, Gig $gig): RedirectResponse
    {
        $gig->update($request->gigAttributes());

        return redirect()->route('admin.dj.gigs.edit', $gig)
            ->with('success', __('dj.gigs.flash.updated'));
    }

    public function destroy(Gig $gig): RedirectResponse
    {
        Gate::authorize('delete', $gig);

        $gig->delete();

        return redirect()->route('admin.dj.gigs.index')->with('success', __('dj.gigs.flash.deleted'));
    }

    /**
     * The DJ settings defaults, so the form can show what "use the default" means
     *
     * @return array<string, mixed>
     */
    protected function defaults(DjSettings $settings): array
    {
        return [
            'requests_mode' => $settings->requests_mode->value,
            'requests_mode_label' => $settings->requests_mode->label(),
            'requests_open_minutes_before' => $settings->requests_open_minutes_before,
            'max_requests_per_visitor' => $settings->max_requests_per_visitor,
            'max_pending_requests' => $settings->max_pending_requests,
            'allow_custom_requests' => $settings->allow_custom_requests,
            'show_public_list' => $settings->show_public_list,
            'allow_upvotes' => $settings->allow_upvotes,
            'show_pass_labels' => $settings->show_pass_labels,
            'show_tracklist' => $settings->show_tracklist,
            'tracklist_per_page' => $settings->tracklist_per_page,
            'tracklist_delay_minutes' => $settings->tracklist_delay_minutes,
        ];
    }
}

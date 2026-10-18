<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Admin\Dj\HistorySessionResource;
use App\Http\Resources\Tenant\Admin\Dj\PlayedTrackResource;
use App\Models\Dj\Gig;
use App\Models\Dj\HistorySession;
use App\Models\Settings\DjSettings;
use App\Services\Dj\HistoryLinker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Play history: every session, linked to a gig or not, with suggestions for linking them
 */
class HistoryController extends Controller
{
    public function index(Request $request, HistoryLinker $linker, DjSettings $settings): Response
    {
        Gate::authorize('viewAny', HistorySession::class);

        $validated = $request->validate([
            'filter' => ['nullable', 'array'],
            'filter.search' => ['nullable', Rule::string()->max(255)],
            'filter.linked' => ['nullable', Rule::in(['linked', 'unlinked'])],
            'filter.stats' => ['nullable', Rule::in(['included', 'excluded'])],
        ]);
        $filter = $validated['filter'] ?? [];

        $sessions = HistorySession::query()
            ->with(['gig', 'library'])
            ->withCount('plays')
            ->when(filled($filter['search'] ?? null), fn (Builder $query) => $query->whereLike('name', '%'.$filter['search'].'%'))
            ->when(($filter['linked'] ?? null) === 'linked', fn (Builder $query) => $query->whereNotNull('gig_id'))
            ->when(($filter['linked'] ?? null) === 'unlinked', fn (Builder $query) => $query->whereNull('gig_id'))
            ->when(($filter['stats'] ?? null) === 'included', fn (Builder $query) => $query->where('include_in_stats', true))
            ->when(($filter['stats'] ?? null) === 'excluded', fn (Builder $query) => $query->where('include_in_stats', false))
            ->latest('started_at')
            ->paginate(25)
            ->appends($request->query());

        return Inertia::render('admin/dj/history/Index', [
            'filter' => array_filter($filter),
            'sessions' => HistorySessionResource::collection($sessions),
            // Working these out looks at every session, so the page shows first
            'suggestedGigs' => Inertia::defer(fn () => $linker->suggestedGigs()->map(fn (Collection $group) => [
                'session_ids' => $group->modelKeys(),
                'names' => $group->pluck('name')->all(),
                'plays_count' => (int) $group->sum('plays_count'),
                'started_at' => $group->first()?->started_at?->toIso8601String(),
                'ended_at' => $group->last()?->ended_at?->toIso8601String(),
            ])),
            'suggestedLinks' => Inertia::defer(fn () => $linker->suggestedLinks()->map(fn (array $suggestion) => [
                'session' => (new HistorySessionResource($suggestion['session']))->resolve(),
                'gig' => ['id' => $suggestion['gig']->id, 'name' => $suggestion['gig']->name],
            ])),
            'gigs' => Gig::query()->latest('starts_at')->limit(200)->get()
                ->map(fn (Gig $gig) => [
                    'value' => $gig->id,
                    'label' => $gig->name.' · '.$gig->starts_at->setTimezone($gig->timezone)->format('j M Y'),
                ]),
            'timezone' => $settings->default_timezone,
            'canManage' => $request->user()?->can('update', HistorySession::class) ?? false,
        ]);
    }

    public function show(Request $request, HistorySession $session, DjSettings $settings): Response
    {
        Gate::authorize('view', $session);

        $session->load(['gig', 'library'])->loadCount('plays');
        $plays = $session->plays()->with(['session', 'song.artist', 'track.artist', 'track.canonicalTrack'])->get();

        return Inertia::render('admin/dj/history/Show', [
            'session' => new HistorySessionResource($session),
            'plays' => PlayedTrackResource::collection($plays),
            'timezone' => $session->gig !== null ? $session->gig->timezone : $settings->default_timezone,
        ]);
    }
}

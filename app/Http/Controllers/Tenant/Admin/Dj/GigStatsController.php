<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\GigStatsQuery;
use App\Models\Dj\Gig;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A gig's stats (#126), during or after it
 */
class GigStatsController extends Controller
{
    public function show(Request $request, Gig $gig, GigStatsQuery $query): Response
    {
        Gate::authorize('viewStats', $gig);

        return Inertia::render('admin/dj/gig/Stats', [
            'gig' => [
                'id' => $gig->id,
                'name' => $gig->name,
                'timezone' => $gig->timezone,
                'starts_at' => $gig->starts_at->toIso8601String(),
                'ends_at' => $gig->ends_at->toIso8601String(),
                // The page keeps up while the gig is on (or pinned as live)
                'is_live' => $gig->is_pinned || $gig->isOnAt(CarbonImmutable::now()),
                'edit_url' => $request->user()?->can('update', $gig) ? route('admin.dj.gigs.edit', $gig) : null,
            ],
            'stats' => $query->handle($gig),
        ]);
    }
}

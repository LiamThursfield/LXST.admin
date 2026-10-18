<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Models\Dj\Gig;
use App\Services\Dj\CurrentGig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Pinning a gig as live makes it the current gig, whatever the time
 */
class GigPinController extends Controller
{
    public function store(Gig $gig, CurrentGig $currentGig): RedirectResponse
    {
        Gate::authorize('update', $gig);

        $currentGig->pin($gig);

        return redirect()->back()->with('success', __('dj.gigs.flash.pinned', ['name' => $gig->name]));
    }

    public function destroy(Gig $gig, CurrentGig $currentGig): RedirectResponse
    {
        Gate::authorize('update', $gig);

        $currentGig->unpin($gig);

        return redirect()->back()->with('success', __('dj.gigs.flash.unpinned', ['name' => $gig->name]));
    }
}

<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\HistoryBulkRequest;
use App\Models\Dj\Gig;
use App\Services\Dj\HistoryLinker;
use Illuminate\Http\RedirectResponse;

/**
 * Linking selected (or suggested) history sessions to gigs
 */
class HistoryBulkController extends Controller
{
    public function link(HistoryBulkRequest $request, HistoryLinker $linker): RedirectResponse
    {
        $gig = Gig::query()->whereKey((int) $request->validated('gig_id'))->firstOrFail();
        $sessions = $request->sessions();

        $linker->link($sessions, $gig);

        return redirect()->back()->with('success', trans_choice('dj.history.flash.linked', $sessions->count(), [
            'count' => $sessions->count(),
            'gig' => $gig->name,
        ]));
    }

    /**
     * A new gig spanning the sessions, linked to them
     */
    public function createGig(HistoryBulkRequest $request, HistoryLinker $linker): RedirectResponse
    {
        $gig = $linker->createGig($request->sessions(), $request->validated('name'));

        return redirect()->route('admin.dj.gigs.edit', $gig)->with('success', __('dj.history.flash.gig_created'));
    }

    public function unlink(HistoryBulkRequest $request, HistoryLinker $linker): RedirectResponse
    {
        $sessions = $request->sessions();

        $linker->unlink($sessions);

        return redirect()->back()->with('success', trans_choice('dj.history.flash.unlinked', $sessions->count(), ['count' => $sessions->count()]));
    }

    public function stats(HistoryBulkRequest $request, HistoryLinker $linker): RedirectResponse
    {
        $linker->setIncludeInStats($request->sessions(), $request->boolean('include_in_stats'));

        return redirect()->back()->with('success', __('dj.history.flash.stats_updated'));
    }
}

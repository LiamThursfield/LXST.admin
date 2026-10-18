<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\HistorySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\GigPlayStoreRequest;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Services\Dj\HistoryLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Songs added by hand to what a gig played, e.g. for gigs played on other gear
 */
class GigPlayController extends Controller
{
    public function store(GigPlayStoreRequest $request, Gig $gig, HistoryLinker $linker): RedirectResponse
    {
        $song = CanonicalTrack::query()->whereKey((int) $request->validated('canonical_track_id'))->firstOrFail();

        $linker->addManualPlay($gig, $song, $request->playedAt($gig));

        return redirect()->back()->with('success', __('dj.history.flash.play_added'));
    }

    /**
     * Imported plays come back on a re-import, so only plays added by hand can be removed
     */
    public function destroy(Gig $gig, HistoryPlay $play, HistoryLinker $linker): RedirectResponse
    {
        Gate::authorize('update', $gig);

        abort_unless($play->session->gig_id === $gig->id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($play->session->source === HistorySource::Manual, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, __('dj.history.errors.imported_play'));

        $linker->removeManualPlay($play);

        return redirect()->back()->with('success', __('dj.history.flash.play_removed'));
    }
}

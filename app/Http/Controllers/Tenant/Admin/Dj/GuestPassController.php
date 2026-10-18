<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\GuestPassRequest;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Services\Dj\GuestPasses\GuestPasswordGenerator;
use App\Services\Dj\SongLists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Guest passes on a setlist or a gig's requests: labelled, simple passwords for people without a
 * login
 */
class GuestPassController extends Controller
{
    public function store(GuestPassRequest $request, SongList $songList, SongLists $songLists, GuestPasswordGenerator $generator): RedirectResponse
    {
        return $this->add($request, $songLists, $generator);
    }

    /**
     * Adds a pass to the gig's requests list, making the list if it's the first
     */
    public function storeForGig(GuestPassRequest $request, Gig $gig, SongLists $songLists, GuestPasswordGenerator $generator): RedirectResponse
    {
        return $this->add($request, $songLists, $generator);
    }

    public function update(GuestPassRequest $request, GuestPass $guestPass, GuestPasswordGenerator $generator): RedirectResponse
    {
        $guestPass->update($request->passAttributes($request->boolean('regenerate_password') ? $generator->generate() : null));

        return redirect()->back()->with('success', __('dj.guest_passes.flash.updated'));
    }

    /**
     * The songs it added stay, without its label
     */
    public function destroy(GuestPass $guestPass): RedirectResponse
    {
        Gate::authorize('delete', $guestPass);

        $guestPass->delete();

        return redirect()->back()->with('success', __('dj.guest_passes.flash.deleted'));
    }

    protected function add(GuestPassRequest $request, SongLists $songLists, GuestPasswordGenerator $generator): RedirectResponse
    {
        $request->songList($songLists)->guestPasses()->create($request->passAttributes($generator->generate()));

        return redirect()->back()->with('success', __('dj.guest_passes.flash.created'));
    }
}

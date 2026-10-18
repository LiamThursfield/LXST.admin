<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryTrack;
use App\Models\User;
use App\Services\Dj\Matching\CanonicalMerger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LibraryTrackSplitController extends Controller
{
    /**
     * Gives the library track a canonical track of its own, after a wrong match
     */
    public function store(Request $request, LibraryTrack $track, CanonicalMerger $merger): RedirectResponse
    {
        Gate::authorize('update', CanonicalTrack::class);

        /** @var User $user */
        $user = $request->user();
        $merger->splitTrack($track, $user);

        return redirect()->back()->with('success', __('dj.matching.flash.split'));
    }
}

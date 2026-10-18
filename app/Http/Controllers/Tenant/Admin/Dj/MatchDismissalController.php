<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\MatchDismissalStoreRequest;
use App\Models\User;
use App\Services\Dj\Matching\CanonicalMerger;
use Illuminate\Http\RedirectResponse;

class MatchDismissalController extends Controller
{
    /**
     * Stops suggesting a pair that isn't the same
     */
    public function store(MatchDismissalStoreRequest $request, CanonicalMerger $merger): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $merger->dismiss($request->record(), $request->integer('first_id'), $request->integer('second_id'), $user);

        return redirect()->back()->with('success', __('dj.matching.flash.dismissed'));
    }
}

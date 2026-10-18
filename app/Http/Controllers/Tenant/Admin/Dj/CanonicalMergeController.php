<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\CanonicalMergeStoreRequest;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\User;
use App\Services\Dj\Matching\CanonicalMerger;
use Illuminate\Http\RedirectResponse;

class CanonicalMergeController extends Controller
{
    /**
     * Merges the source record into the target, which is kept
     */
    public function store(CanonicalMergeStoreRequest $request, CanonicalMerger $merger): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $targetId = $request->integer('target_id');
        $sourceId = $request->integer('source_id');

        $merge = $request->record() === CanonicalRecord::Artist
            ? $merger->mergeArtists(CanonicalArtist::query()->findOrFail($targetId), CanonicalArtist::query()->findOrFail($sourceId), $user)
            : $merger->mergeTracks(CanonicalTrack::query()->findOrFail($targetId), CanonicalTrack::query()->findOrFail($sourceId), $user);

        return redirect()->back()->with('success', __('dj.matching.flash.merged', [
            'source' => $merge->source_name,
            'target' => $merge->target_name,
        ]));
    }
}

<?php

namespace App\Services\Dj;

use App\Models\Dj\Gig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The one place that decides the current gig, for the API, the hosted request page and now
 * playing: the gig pinned as live, otherwise the gig on now (the one that started most recently,
 * as gigs can overlap), otherwise the next upcoming gig.
 */
class CurrentGig
{
    public function resolve(?CarbonInterface $at = null): ?Gig
    {
        $at ??= CarbonImmutable::now();

        return Gig::query()->where('is_pinned', true)->first()
            ?? Gig::query()->onAt($at)->latest('starts_at')->first()
            ?? Gig::query()->where('starts_at', '>', $at)->oldest('starts_at')->first();
    }

    /**
     * Pins the gig as live, unpinning any other, so it's current whatever the time
     */
    public function pin(Gig $gig): void
    {
        DB::transaction(function () use ($gig): void {
            Gig::query()->whereKeyNot($gig->id)->where('is_pinned', true)->update(['is_pinned' => false]);
            $gig->update(['is_pinned' => true]);
        });
    }

    public function unpin(Gig $gig): void
    {
        $gig->update(['is_pinned' => false]);
    }
}

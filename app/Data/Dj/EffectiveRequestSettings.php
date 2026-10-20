<?php

namespace App\Data\Dj;

use App\Enums\Dj\RequestsMode;
use Spatie\LaravelData\Data;

/**
 * A gig's request settings once the DJ settings defaults are filled in
 */
class EffectiveRequestSettings extends Data
{
    public function __construct(
        public RequestsMode $requests_mode,
        public int $requests_open_minutes_before,
        /** 0 means no limit */
        public int $max_requests_per_visitor,
        /** 0 means no limit */
        public int $max_pending_requests,
        public bool $allow_custom_requests,
        public bool $show_public_list,
        public bool $allow_upvotes,
        /** Whether the public request list shows the guest pass that made each request */
        public bool $show_pass_labels,
        /** Whether the gig's tracklist (now playing and recently played) is public */
        public bool $show_tracklist,
        public int $tracklist_per_page,
        /** How long a track is held back from the public tracklist */
        public int $tracklist_delay_minutes,
    ) {}
}

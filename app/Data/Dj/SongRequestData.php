<?php

namespace App\Data\Dj;

use App\Models\Dj\GuestPass;
use Spatie\LaravelData\Data;

/**
 * A visitor's song request: a library song, or a custom artist (and maybe title)
 */
class SongRequestData extends Data
{
    public function __construct(
        public ?int $canonical_track_id,
        public ?string $custom_artist,
        public ?string $custom_title,
        public ?string $requester_name,
        public ?string $message,
        /** Whether the name can show on the public request list */
        public bool $show_requester_name,
        /** The hashed visitor, for per-visitor limits and one vote each */
        public ?string $visitor_hash,
        /** The guest pass used, when the gig's requests need one */
        public ?GuestPass $guest_pass = null,
    ) {}
}

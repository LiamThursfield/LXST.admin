<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Library Imports
    |--------------------------------------------------------------------------
    |
    | Uploaded Rekordbox files are kept on this (private) disk only until their
    | import finishes. Imports run on the queue, a chunk of tracks at a time.
    |
    */

    'imports' => [
        'disk' => env('DJ_IMPORTS_DISK', 'local'),
        'max_size_kb' => (int) env('DJ_IMPORTS_MAX_SIZE_KB', 100 * 1024),
        'chunk_size' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical Matching
    |--------------------------------------------------------------------------
    |
    | Records the automatic rules don't merge, but whose normalised names are at
    | least this alike (0 to 1), are suggested for someone to review.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Play History
    |--------------------------------------------------------------------------
    |
    | Imported sessions count in stats when they have at least this many plays,
    | so a quick check of a track doesn't. Sessions less than `gig_gap_minutes`
    | apart are suggested as one gig, as Rekordbox starts a new session whenever
    | it's relaunched.
    |
    */

    'history' => [
        'min_plays_for_stats' => (int) env('DJ_HISTORY_MIN_PLAYS_FOR_STATS', 5),
        'gig_gap_minutes' => (int) env('DJ_HISTORY_GIG_GAP_MINUTES', 60),
        // A gig's live session is covered by an imported one within this many minutes of it (#159)
        'live_overlap_minutes' => (int) env('DJ_HISTORY_LIVE_OVERLAP_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    |
    | Request context and library search are cached for `cache_seconds`, and
    | refreshed at once by any DJ admin change. Library search allows
    | `search_per_minute` requests per IP address.
    |
    */

    'api' => [
        'cache_seconds' => (int) env('DJ_API_CACHE_SECONDS', 30),
        'search_per_minute' => (int) env('DJ_API_SEARCH_PER_MINUTE', 60),
        'search_limit' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Song Requests
    |--------------------------------------------------------------------------
    |
    | A basic spam check, as for CRM forms: sites render a hidden honeypot field
    | (left empty by people) and send back the token from the gig's details. A
    | filled honeypot, a request sooner than `min_seconds` after the token, a
    | tampered token or a bot user agent is dropped, with an accepted-looking
    | response. Each visitor may send `rate_limit_per_minute` requests and
    | `upvotes_per_minute` upvotes per gig.
    |
    */

    'requests' => [
        'honeypot_field' => 'website_url',
        'token_field' => 'request_token',
        'min_seconds' => (int) env('DJ_REQUESTS_MIN_SECONDS', 3),
        'rate_limit_per_minute' => (int) env('DJ_REQUESTS_PER_MINUTE', 10),
        'upvotes_per_minute' => (int) env('DJ_REQUEST_UPVOTES_PER_MINUTE', 30),
        // How many played requests the public list shows, newest first
        'played_shown' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest Passes
    |--------------------------------------------------------------------------
    |
    | Guests exchange a pass's password for a token that lasts `token_minutes`.
    | After `max_attempts` wrong passwords for a list from one IP address, that
    | address is locked out of the list for `lockout_minutes`.
    |
    */

    'guest_passes' => [
        'token_minutes' => (int) env('DJ_GUEST_TOKEN_MINUTES', 720),
        'max_attempts' => (int) env('DJ_GUEST_PASS_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('DJ_GUEST_PASS_LOCKOUT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Now Playing
    |--------------------------------------------------------------------------
    |
    | The CLI tool reports each track as it's played. The same track reported
    | again within `duplicate_minutes` is ignored, so retries and both decks
    | reporting don't add it twice.
    |
    */

    'now_playing' => [
        'duplicate_minutes' => (int) env('DJ_NOW_PLAYING_DUPLICATE_MINUTES', 5),
        // The latest track counts as playing now for this long after it started (#125)
        'current_minutes' => (int) env('DJ_NOW_PLAYING_CURRENT_MINUTES', 15),
    ],

    'matching' => [
        'suggestion_threshold' => (float) env('DJ_MATCHING_SUGGESTION_THRESHOLD', 0.85),
    ],

];

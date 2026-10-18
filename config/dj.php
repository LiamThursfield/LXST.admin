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
    ],

    'matching' => [
        'suggestion_threshold' => (float) env('DJ_MATCHING_SUGGESTION_THRESHOLD', 0.85),
    ],

];

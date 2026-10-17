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

];

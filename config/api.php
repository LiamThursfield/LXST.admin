<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant API Rate Limit
    |--------------------------------------------------------------------------
    |
    | The number of requests per minute a single IP address may make to one
    | tenant's public API (the `tenant-api` rate limiter).
    |
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),

];

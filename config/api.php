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

    /*
    |--------------------------------------------------------------------------
    | Form Submissions
    |--------------------------------------------------------------------------
    |
    | Submitting a CRM form has its own, stricter rate limit (per tenant and IP).
    |
    | A basic spam check runs on every submission: websites render a hidden
    | honeypot field (which people leave empty) and send back the token they
    | received with the form definition. A filled honeypot, a submission made
    | sooner than `min_seconds` after the form was loaded, or a tampered token
    | marks the submission as spam.
    |
    */

    'form_submissions' => [
        'rate_limit_per_minute' => (int) env('API_FORM_SUBMISSIONS_PER_MINUTE', 10),
        'honeypot_field' => 'website_url',
        'token_field' => 'form_token',
        'min_seconds' => (int) env('API_FORM_SUBMISSIONS_MIN_SECONDS', 3),
    ],

];

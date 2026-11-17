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

    /*
    |--------------------------------------------------------------------------
    | Captcha
    |--------------------------------------------------------------------------
    |
    | Forms (and song requests) can also ask for a captcha (#74), set up per
    | tenant in the integration settings. Sites render the provider's widget
    | and send its token in `token_field`. The provider gets `timeout_seconds`
    | to answer; if it can't, the submission is kept but marked as spam.
    |
    */

    'captcha' => [
        'token_field' => 'captcha_token',
        'timeout_seconds' => (int) env('API_CAPTCHA_TIMEOUT_SECONDS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Views
    |--------------------------------------------------------------------------
    |
    | Recording a page view has its own rate limit, per tenant and visitor (the
    | IP address the site sends, or the request's own).
    |
    */

    'page_views' => [
        'rate_limit_per_minute' => (int) env('API_PAGE_VIEWS_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Tokens
    |--------------------------------------------------------------------------
    |
    | Routes for trusted clients (e.g. the Rekordbox CLI tool) need a tenant API
    | token with the right ability, and are rate limited per token (the
    | `tenant-api-token` rate limiter).
    |
    */

    'tokens' => [
        'rate_limit_per_minute' => (int) env('API_TOKENS_PER_MINUTE', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | The MCP server (`/api/mcp`) lets an AI assistant manage the site's content
    | with a tenant API token that has `mcp:*` abilities. Every request counts
    | towards the token's rate limit above; tools that change something have
    | their own, stricter limit per token. Every tool call is audit logged, and
    | log entries are pruned after `audit_retention_days`.
    |
    | A new token with an MCP ability suggests an expiry `suggested_expiry_days`
    | ahead, which can be cleared.
    |
    */

    'mcp' => [
        'writes_per_minute' => (int) env('API_MCP_WRITES_PER_MINUTE', 30),
        'audit_retention_days' => (int) env('API_MCP_AUDIT_RETENTION_DAYS', 180),
        'suggested_expiry_days' => 90,
    ],

];

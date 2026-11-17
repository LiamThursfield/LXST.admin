<?php

/*
|--------------------------------------------------------------------------
| Dashboard Language Lines
|--------------------------------------------------------------------------
|
| Strings for the central admin's dashboard.
|
*/

return [
    'stats' => [
        'active' => 'Active tenants',
        'new' => 'New tenants',
        'new_help' => 'In the last :days days',
        'disabled' => 'Disabled tenants',
        'failed_jobs' => 'Failed jobs',
        'pending_jobs' => ':count waiting in the queue',
    ],

    'attention' => [
        'title' => 'Needs attention',
        'none' => 'Nothing needs attention.',
    ],

    'issues' => [
        'stripe' => 'Bookings is on, but Stripe can\'t take payments yet',
        'no_fee' => 'No platform fee set',
        'no_own_domain' => 'No domain of its own',
        'disabled' => 'Disabled since :date',
    ],

    'recent' => [
        'title' => 'Newest tenants',
        'none' => 'No tenants yet.',
        'all' => 'All tenants',
    ],

    'modules' => [
        'title' => 'Modules',
        'help' => 'Active tenants using each module',
    ],

    'industries' => [
        'title' => 'Industries',
        'help' => 'Active tenants in each industry',
        'none' => 'No active tenants yet.',
    ],
];

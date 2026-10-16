<?php

/*
|--------------------------------------------------------------------------
| CMS Language Lines
|--------------------------------------------------------------------------
|
| Strings for the CMS module (redirects, and later pages, templates and layouts).
|
*/

return [
    'redirects' => [
        'index' => [
            'title' => 'Redirects',
            'create' => 'New redirect',
            'empty' => 'No redirects yet.',
        ],
        'create' => [
            'title' => 'New redirect',
            'submit' => 'Create redirect',
        ],
        'edit' => [
            'title' => 'Edit redirect',
            'submit' => 'Save changes',
        ],
        'columns' => [
            'from' => 'From',
            'to' => 'To',
            'type' => 'Type',
            'status' => 'Status',
        ],
        'filters' => [
            'search' => 'Search URLs',
            'status' => 'Status',
        ],
        'types' => [
            'permanent' => 'Permanent (301)',
            'temporary' => 'Temporary (302)',
        ],
        'sections' => [
            'urls' => 'URLs',
            'behaviour' => 'Behaviour',
            'schedule' => 'Schedule',
        ],
        'fields' => [
            'url_from' => 'From',
            'url_from_help' => 'A path on the site, e.g. /old-page. A trailing slash is removed.',
            'url_to' => 'To',
            'url_to_help' => 'A path on the site, e.g. /new-page, or a full URL to another site.',
            'is_enabled' => 'Enabled',
            'is_enabled_help' => 'Disabled redirects are kept but not applied.',
            'is_permanent' => 'Permanent',
            'is_permanent_help' => 'Permanent (301) tells search engines the page has moved for good. Use temporary (302) if it may come back.',
            'published_at' => 'Starts',
            'published_at_help' => 'Leave empty to start straight away. Uses your timezone.',
            'expired_at' => 'Ends',
            'expired_at_help' => 'Leave empty to keep it running. Uses your timezone.',
        ],
        'external' => 'External link',
        'starts' => 'Starts :date',
        'ended' => 'Ended :date',
        'confirm_delete' => 'Delete the redirect from :url?',
        'flash' => [
            'created' => 'Redirect created.',
            'updated' => 'Redirect updated.',
            'deleted' => 'Redirect deleted.',
        ],
        'validation' => [
            'from_taken' => 'There is already a redirect from this URL.',
            'same_url' => 'The redirect can\'t go to the URL it comes from.',
            'from_is_destination' => 'The redirect from :url already ends here, so this would create a chain. Point that redirect straight to the final URL instead.',
            'to_is_redirected' => ':url is already redirected, so this would create a chain. Point this redirect straight to the final URL instead.',
            'to_invalid' => 'Enter a valid URL.',
            'expires_before_publish' => 'The end must be after the start.',
        ],
    ],
];

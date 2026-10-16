<?php

/*
|--------------------------------------------------------------------------
| CMS Language Lines
|--------------------------------------------------------------------------
|
| Strings for the CMS module (templates, redirects, and later pages and layouts).
|
*/

return [
    'templates' => [
        'index' => [
            'title' => 'Templates',
            'create' => 'New template',
            'empty' => 'No templates yet.',
        ],
        'create' => [
            'title' => 'New template',
            'submit' => 'Create template',
        ],
        'edit' => [
            'title' => 'Edit :name',
            'submit' => 'Save changes',
        ],
        'show' => [
            'title' => ':name',
        ],
        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'slug' => 'Slug',
            'fields' => 'Fields',
        ],
        'filters' => [
            'name' => 'Search names',
            'type' => 'Type',
        ],
        'sections' => [
            'details' => 'Details',
        ],
        'fields' => [
            'type' => 'Type',
            'type_help' => 'Pages and layouts can use any field. Components and repeaters are reusable groups of fields placed inside other templates.',
            'type_in_use' => 'This template is used by other templates, so its type can\'t be changed.',
            'name' => 'Name',
            'slug' => 'Slug',
            'slug_help' => 'Leave empty to make one from the name. Must be unique for this type of template.',
            'description' => 'Description',
        ],
        'builder' => [
            'title' => 'Fields',
            'intro' => 'The fields content editors fill in. Drag to reorder.',
            'empty' => 'No fields yet.',
            'add' => 'Add field',
            'drag' => 'Drag to reorder',
            'remove' => 'Remove field',
            'type' => 'Type',
            'name' => 'Name',
            'slug' => 'Slug',
            'slug_help' => 'Content is saved under this key, e.g. for the API. Leave empty to make one from the name.',
            'description' => 'Help text',
            'description_help' => 'Shown to content editors under the field.',
            'is_required' => 'Required',
            'max_length' => 'Maximum length',
            'rows' => 'Rows',
            'min' => 'Minimum',
            'max' => 'Maximum',
            'template_id' => 'Template',
            'coming_soon' => 'coming soon',
        ],
        'confirm_delete' => 'Delete the :name template?',
        'flash' => [
            'created' => 'Template created.',
            'updated' => 'Template updated.',
            'deleted' => 'Template deleted.',
        ],
        'validation' => [
            'slug_taken' => 'Another template of this type already uses this slug.',
            'duplicate_field_slug' => 'Each field needs its own slug.',
            'type_in_use' => 'This template is used by other templates, so its type can\'t be changed.',
            'field_type_not_allowed' => ':template templates can\'t have :field fields.',
            'field_type_unavailable' => ':field fields can\'t be added yet.',
            'nested_template' => 'Choose a :type template (not this one).',
            'max_below_min' => 'The maximum can\'t be below the minimum.',
            'in_use' => 'This template is used by other templates, so it can\'t be deleted.',
        ],
    ],

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

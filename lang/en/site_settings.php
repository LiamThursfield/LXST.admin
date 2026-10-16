<?php

/*
|--------------------------------------------------------------------------
| Site Settings Language Lines
|--------------------------------------------------------------------------
|
| Strings for the tenant's site settings (general, integrations). Account settings such as
| the user's profile are in settings.php.
|
*/

return [
    'title' => 'Site settings',
    'save_changes' => 'Save changes',
    'read_only' => 'You can view these settings, but not change them.',

    'general' => [
        'nav' => 'General',
        'title' => 'General',
        'description' => 'The basics about this site.',
        'site_name' => 'Site name',
        'site_name_help' => 'Shown in page titles and anywhere the site refers to itself.',
        'updated' => 'General settings saved.',
        'validation' => [
            'site_name_required' => 'Enter a site name.',
        ],
    ],

    'integrations' => [
        'nav' => 'Integrations',
        'title' => 'Integrations',
        'description' => 'Connect the site to third-party services.',
        'gtm_id' => 'Google Tag Manager container ID',
        'gtm_id_help' => 'For example GTM-ABC1234. Leave empty to turn Google Tag Manager off.',
        'updated' => 'Integration settings saved.',
        'validation' => [
            'gtm_id_format' => 'Enter a container ID like GTM-ABC1234.',
        ],
    ],
];

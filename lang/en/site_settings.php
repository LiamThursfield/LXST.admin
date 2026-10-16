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

    'theme' => [
        'nav' => 'Theme',
        'title' => 'Theme',
        'description' => 'Branding for the admin and the website.',
        'updated' => 'Theme saved.',
        'branding' => 'Branding',
        'logo' => 'Logo',
        'logo_help' => 'Shown at the top of the admin sidebar, and available to the website.',
        'favicon' => 'Favicon',
        'favicon_help' => 'The small icon in browser tabs. A square PNG or SVG works best.',
        'admin_colors' => 'Admin colours',
        'admin_colors_description' => 'Colour palettes for buttons, links and backgrounds in the admin.',
        'primary_color' => 'Primary colour',
        'neutral_color' => 'Neutral colour',
        'site_colors' => 'Website colours',
        'site_colors_description' => 'For the website to use through the API. Leave a colour empty to let the website decide.',
        'reset_site_colors' => 'Reset to defaults',
        'palettes' => [
            'red' => 'Red',
            'orange' => 'Orange',
            'amber' => 'Amber',
            'yellow' => 'Yellow',
            'lime' => 'Lime',
            'green' => 'Green',
            'emerald' => 'Emerald',
            'teal' => 'Teal',
            'cyan' => 'Cyan',
            'sky' => 'Sky',
            'blue' => 'Blue',
            'indigo' => 'Indigo',
            'violet' => 'Violet',
            'purple' => 'Purple',
            'fuchsia' => 'Fuchsia',
            'pink' => 'Pink',
            'rose' => 'Rose',
            'slate' => 'Slate',
            'gray' => 'Gray',
            'zinc' => 'Zinc',
            'neutral' => 'Neutral',
            'stone' => 'Stone',
        ],
        'site_color_labels' => [
            'base' => 'Base',
            'base_contrast' => 'Base text',
            'base_subtle' => 'Subtle base',
            'base_subtle_contrast' => 'Subtle base text',
            'primary' => 'Primary',
            'primary_contrast' => 'Primary text',
            'primary_hover' => 'Primary hover',
            'primary_hover_contrast' => 'Primary hover text',
            'secondary' => 'Secondary',
            'secondary_contrast' => 'Secondary text',
            'secondary_hover' => 'Secondary hover',
            'secondary_hover_contrast' => 'Secondary hover text',
            'accent' => 'Accent',
            'accent_contrast' => 'Accent text',
            'accent_hover' => 'Accent hover',
            'accent_hover_contrast' => 'Accent hover text',
        ],
        'validation' => [
            'image' => 'Choose an image from the File Manager.',
            'hex' => 'Enter a hex colour like #2563EB.',
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

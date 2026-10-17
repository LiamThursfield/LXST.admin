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

    'email_previews' => [
        'nav' => 'Email previews',
        'title' => 'Email previews',
        'description' => 'See the emails this site sends, filled in with sample data. Nothing is sent.',
        'choose' => 'Choose an email to preview.',
        'open' => 'Open in a new tab',
        'frame_title' => 'Preview of :email',
        'groups' => [
            'users' => 'Users',
            'settings' => 'Settings',
            'crm' => 'CRM',
        ],
        'emails' => [
            'password_setup' => 'Password setup and reset',
            'mail_test' => 'Mail settings test',
            'form_submitted' => 'New submission: :form',
        ],
        'sample' => [
            'answer' => 'A sample answer',
            'long_answer' => 'A longer sample answer, as someone might type into a larger box on the form.',
        ],
    ],

    'mail' => [
        'nav' => 'Mail',
        'title' => 'Mail',
        'description' => 'Send this site\'s emails (e.g. form notifications) through your own email account.',
        'updated' => 'Mail settings saved.',
        'driver' => 'Send email with',
        'driver_help' => 'Leave as the default to use this platform\'s own mailer.',
        'drivers' => [
            'default' => 'The platform\'s default mailer',
            'smtp' => 'SMTP',
            'mailgun' => 'Mailgun',
            'ses' => 'Amazon SES',
        ],
        'sender' => 'Sender',
        'from_address' => 'From address',
        'from_name' => 'From name',
        'from_name_placeholder' => 'Defaults to :name',
        'connection' => 'Connection',
        'smtp_host' => 'Host',
        'smtp_port' => 'Port',
        'smtp_port_help' => 'Port 465 connects over SSL. Other ports (usually 587) use STARTTLS when the server offers it.',
        'smtp_username' => 'Username',
        'smtp_password' => 'Password',
        'mailgun_domain' => 'Domain',
        'mailgun_secret' => 'API key',
        'mailgun_endpoint' => 'Region',
        'mailgun_endpoints' => [
            'api.mailgun.net' => 'US (api.mailgun.net)',
            'api.eu.mailgun.net' => 'EU (api.eu.mailgun.net)',
        ],
        'ses_key' => 'Access key ID',
        'ses_secret' => 'Secret access key',
        'ses_region' => 'Region',
        'ses_region_placeholder' => 'e.g. eu-west-2',
        'secret_saved' => 'Saved. Leave empty to keep it, or enter a new one to replace it.',
        'secret_placeholder' => 'Saved and hidden',
        'test' => 'Send a test email',
        'test_description' => 'Sends a test email with the saved settings.',
        'test_save_first' => 'Save your changes before sending a test email.',
        'test_to' => 'Send to',
        'test_send' => 'Send test email',
        'test_sent' => 'Test email sent to :to.',
        'test_failed' => 'The test email couldn\'t be sent: :error',
        'test_mail' => [
            'subject' => 'Test email from :site',
            'heading' => 'It works',
            'body' => 'This test email was sent by :site using :mailer. Emails from the site will be sent the same way.',
        ],
        'validation' => [
            'ses_region' => 'Enter an AWS region like eu-west-2.',
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

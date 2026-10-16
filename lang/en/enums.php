<?php

/*
|--------------------------------------------------------------------------
| Enum Labels
|--------------------------------------------------------------------------
|
| Labels for enums that use the HasLabels trait, keyed by the snake case enum
| class name and then the case value, e.g.
|
| 'task_definition_status' => [
|     'active' => 'Active',
| ],
|
| Values without an entry fall back to a title case version of the value.
|
*/

return [
    'form_field_type' => [
        'text' => 'Text',
        'textarea' => 'Text area',
        'number' => 'Number',
        'email' => 'Email',
        'hidden' => 'Hidden',
        'crm_email' => 'Contact: email',
        'crm_title' => 'Contact: title',
        'crm_first_name' => 'Contact: first name',
        'crm_last_name' => 'Contact: last name',
        'crm_telephone' => 'Contact: telephone',
    ],
    'organisation_unit_type' => [
        'company' => 'Company',
        'location' => 'Location',
        'department' => 'Department',
    ],
    'redirect_status' => [
        'active' => 'Active',
        'scheduled' => 'Scheduled',
        'expired' => 'Expired',
        'disabled' => 'Disabled',
    ],

    'role' => [
        'super-admin' => 'Super Admin',
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ],
    'social_media' => [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'snapchat' => 'Snapchat',
        'tiktok' => 'TikTok',
        'twitter' => 'Twitter',
        'whatsapp' => 'WhatsApp',
        'youtube' => 'YouTube',
    ],
    'task_definition_status' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'archived' => 'Archived',
    ],
    'task_occurrence_status' => [
        'pending' => 'Pending',
        'on-track' => 'On Track',
        'met' => 'Met',
        'missed' => 'Missed',
        'skipped' => 'Skipped',
    ],
    'tenant_module' => [
        'cms' => 'CMS',
        'crm' => 'CRM',
        'tracker' => 'Tracker',
    ],
    'tracker_aggregation' => [
        'sum' => 'Sum',
        'last' => 'Last',
        'min' => 'Min',
        'max' => 'Max',
        'count' => 'Count',
        'any' => 'Any',
    ],
    'tracker_comparator' => [
        'none' => 'None',
        'boolean' => 'Boolean',
        'min' => 'Min',
        'max' => 'Max',
        'between' => 'Between',
    ],
    'tracker_measure' => [
        'boolean' => 'Boolean',
        'count' => 'Count',
        'weight' => 'Weight',
        'volume' => 'Volume',
        'duration' => 'Duration',
        'energy' => 'Energy',
        'clock-time' => 'Clock Time',
    ],
    'tracker_period' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'lifetime' => 'Lifetime',
        'one-off' => 'One Off',
    ],
    'tracker_unit' => [
        'boolean' => 'Boolean',
        'count' => 'Count',
        'kg' => 'Kg',
        'lb' => 'Lb',
        'l' => 'L',
        'ml' => 'Ml',
        'minutes' => 'Minutes',
        'hours' => 'Hours',
        'kcal' => 'Kcal',
        'time-of-day' => 'Time Of Day',
    ],
];

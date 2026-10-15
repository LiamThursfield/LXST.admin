<?php

/*
|--------------------------------------------------------------------------
| CRM Language Lines
|--------------------------------------------------------------------------
|
| Strings for the CRM module. Enum labels (organisation unit types, social
| media) live in enums.php.
|
*/

return [
    'save_changes' => 'Save Changes',

    'address' => [
        'title' => 'Address',
        'description' => 'Leave every field blank for no address.',
        'postcode' => 'Postcode',
        'line_1' => 'Address line 1',
        'line_2' => 'Address line 2',
        'line_3' => 'Address line 3',
        'town_city' => 'Town / City',
        'county' => 'County',
        'country' => 'Country',
    ],

    'organisation_units' => [
        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'parent' => 'Part of',
            'email' => 'Email',
        ],
        'fields' => [
            'type' => 'Type',
            'parent' => 'Part of',
            'parent_description' => 'Companies can be part of a company, locations part of a company, and departments part of a location or department.',
            'name' => 'Name',
            'slug' => 'Slug',
            'slug_description' => 'Used in URLs. Leave blank to generate it from the name.',
            'email' => 'Email',
            'telephone' => 'Telephone',
            'mobile_phone' => 'Mobile phone',
            'notification_emails' => 'Notification emails',
            'notification_emails_description' => 'Who is emailed about enquiries for this unit.',
            'notification_email_placeholder' => 'name@example.com',
            'add_notification_email' => 'Add email',
            'remove_notification_email' => 'Remove email',
            'none' => 'None',
        ],
        'sections' => [
            'details' => 'Details',
            'contact' => 'Contact',
            'socials' => 'Social media',
        ],
        'filters' => [
            'name' => 'Name',
            'type' => 'Type',
        ],
        'index' => [
            'title' => 'Organisation Units',
            'create' => 'New organisation unit',
        ],
        'create' => [
            'title' => 'Create Organisation Unit',
            'submit' => 'Create Organisation Unit',
        ],
        'edit' => [
            'title' => 'Edit Organisation Unit: :name',
        ],
        'show' => [
            'title' => 'Organisation Unit: :name',
        ],
        'validation' => [
            'name_required' => 'Name is required',
            'email_invalid' => 'Invalid email address',
            'parent_type' => 'A :type must be part of one of: :parents.',
            'parent_cycle' => 'An organisation unit cannot be part of itself or one of its own units.',
            'has_children' => 'This organisation unit still has units within it. Move or delete those first.',
        ],
        'confirm_delete' => 'Are you sure you want to delete organisation unit :name?',
        'flash' => [
            'created' => 'Organisation unit created successfully.',
            'updated' => 'Organisation unit updated successfully.',
            'deleted' => 'Organisation unit deleted successfully.',
        ],
    ],
];

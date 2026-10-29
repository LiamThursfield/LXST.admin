<?php

/*
|--------------------------------------------------------------------------
| Common Language Lines
|--------------------------------------------------------------------------
|
| Strings shared across the application, such as generic action labels, layouts
| and shared components. Every file in this directory is shared with the frontend,
| where it can be used via the useTrans() composable, e.g. trans('common.actions.save').
|
*/

return [
    'actions' => [
        'back' => 'Back',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'create' => 'Create',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'more' => 'More actions',
        'save' => 'Save',
        'search' => 'Search',
        'view' => 'View',
    ],

    'builder' => [
        'drag' => 'Drag to reorder',
        'untitled' => 'Untitled',
        'has_errors' => 'Has errors',
        'picker' => [
            'title' => 'Add a field',
            'description' => 'Choose the type of field. You can change it later.',
            'search' => 'Search field types',
            'none' => 'No field types match your search.',
        ],
    ],

    'confirmation' => [
        'default' => 'Are you sure you want to perform this action?',
        'title' => 'Confirm :action',
        'title_fallback' => 'Please confirm',
    ],

    'cookies' => [
        'message' => 'We use first-party cookies to enhance your experience on our website.',
        'accept' => 'Accept',
        'opt_out' => 'Opt out',
    ],

    'dashboard' => [
        'title' => 'Home',
    ],

    'editor' => [
        'save' => 'Save changes',
        'unsaved' => 'Unsaved changes',
        'leave_title' => 'Leave without saving?',
        'leave_description' => 'Your changes on this page haven\'t been saved, and will be lost.',
        'leave' => 'Leave',
        'stay' => 'Keep editing',
    ],

    'footer' => [
        'built_by' => 'Built by LXST.digital • © :year',
    ],

    'notifications' => [
        'title' => 'Notifications',
        'empty' => "You currently don't have any notifications.",
    ],

    'search' => [
        'go_to' => 'Go to',
    ],

    'user_menu' => [
        'notifications' => 'Notifications',
        'profile' => 'Profile',
        'appearance' => 'Appearance',
        'light' => 'Light',
        'dark' => 'Dark',
        'log_out' => 'Log out',
    ],

    'welcome' => [
        'description' => 'A production-ready multi-tenant SaaS powered by Laravel, Inertia, Vue3, Nuxt UI, and Tailwind CSS.',
        'view_on_github' => 'View on GitHub',
    ],
];

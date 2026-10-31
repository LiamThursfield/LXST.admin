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

    'settings' => [
        'read_only' => 'You can view these settings, but not change them.',
        'discard' => 'Discard',
        'save' => 'Save changes',
    ],

    'footer' => [
        'built_by' => 'Built by LXST.digital • © :year',
    ],

    'list' => [
        'columns' => 'Columns',
        'sort' => 'Sort by',
        'sort_by' => 'Sort',
        'sort_ascending' => 'Ascending, switch to descending',
        'sort_descending' => 'Descending, switch to ascending',
        'filters' => 'Filters',
        'more_filters' => 'More filters',
        'show_results' => 'Show results',
        'clear_filters' => 'Clear filters',
        'remove_filter' => 'Remove filter: :filter',
        'selected' => '{1} 1 selected|[2,*] :count selected',
        'clear_selection' => 'Clear selection',
        'select_all' => 'Select all',
        'select_row' => 'Select row',
        'showing' => 'Showing :from–:to of :total',
        'previous' => 'Previous',
        'next' => 'Next',
        'no_matches' => 'Nothing matches these filters',
        'no_matches_description' => 'Try another search, or clear the filters.',
    ],

    'record' => [
        'none' => '—',
        'yes' => 'Yes',
        'no' => 'No',
        'details' => 'Details',
        'activity' => 'Activity',
        'showing' => 'Showing :shown of :total',
        'view_all' => 'View all',
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

<?php

/*
|--------------------------------------------------------------------------
| Tenants Language Lines
|--------------------------------------------------------------------------
|
| Strings for managing tenants in the central admin.
|
*/

return [
    'columns' => [
        'name' => 'Name',
        'id' => 'ID',
        'domains' => 'Domains',
        'modules' => 'Modules',
        'created_at' => 'Created',
    ],

    'filters' => [
        'search' => 'Search name, ID or domain',
        'module' => 'Module',
    ],

    'index' => [
        'title' => 'Tenants',
        'empty' => 'No tenants found.',
    ],

    'show' => [
        'title' => 'Tenant: :name',
        'back' => 'Tenants',
        'details' => 'Details',
        'domains' => 'Domains',
        'modules' => 'Modules',
        'no_domains' => 'This tenant has no domains.',
        'no_modules' => 'No modules are switched on.',
        'open_admin' => 'Open admin',
    ],

    'actions' => [
        'view' => 'View',
    ],
];

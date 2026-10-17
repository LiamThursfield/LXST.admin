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
        'create' => 'New tenant',
        'empty' => 'No tenants found.',
    ],

    'create' => [
        'title' => 'Create Tenant',
        'submit' => 'Create Tenant',
        'provisioning' => 'Creating the tenant\'s database can take a moment.',
    ],

    'edit' => [
        'title' => 'Edit Tenant: :name',
        'submit' => 'Save Changes',
    ],

    'sections' => [
        'tenant' => 'Tenant',
        'modules' => 'Modules',
        'admin' => 'First admin',
        'admin_description' => 'Optional. They\'re made a Super Admin and emailed a link to set their password.',
    ],

    'fields' => [
        'name' => 'Name',
        'name_help' => 'A short handle: lowercase letters, numbers and dashes, e.g. acme-ltd.',
        'domain' => 'Domain',
        'domain_help' => 'The tenant\'s first domain, e.g. acme.example.com. More can be added later.',
        'modules_help' => 'Which optional modules the tenant can use.',
        'admin_first_name' => 'First Name',
        'admin_last_name' => 'Last Name',
        'admin_email' => 'Email',
    ],

    'status' => [
        'active' => 'Active',
        'disabled' => 'Disabled',
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
        'disable' => 'Disable',
        'enable' => 'Enable',
        'delete' => 'Delete',
    ],

    'disable' => [
        'confirm' => 'Disable :name? Its site, admin and API will stop working until it\'s enabled again.',
        'disabled_since' => 'Disabled since :date. Its site, admin and API aren\'t available.',
    ],

    'delete' => [
        'title' => 'Delete :name',
        'warning' => 'This deletes the tenant, its domains and its database, including all of its content and users. It can\'t be undone.',
        'disable_first' => 'Disable the tenant before deleting it.',
        'confirm_label' => 'Type :name to confirm',
        'submit' => 'Delete Tenant',
    ],

    'flash' => [
        'created' => 'Tenant :name created.',
        'created_and_invited' => 'Tenant :name created, and :email has been invited.',
        'create_failed' => 'The tenant couldn\'t be created: :message',
        'updated' => 'Tenant :name updated.',
        'disabled' => 'Tenant :name disabled.',
        'enabled' => 'Tenant :name enabled.',
        'deleted' => 'Tenant :name deleted.',
    ],

    'validation' => [
        'name_format' => 'Use lowercase letters, numbers and dashes only.',
        'domain_format' => 'Enter a domain such as acme.example.com, without http:// or a path.',
        'domain_taken' => 'This domain already belongs to a tenant.',
        'domain_central' => 'This is the central admin\'s domain.',
        'confirm_name' => 'Type the tenant\'s name exactly to confirm.',
        'disable_first' => 'Disable the tenant before deleting it.',
    ],

    // Shown by a disabled tenant's site, admin and API
    'disabled_message' => 'This site is currently unavailable.',
];

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
        'industry' => 'Industry',
        'modules' => 'Modules',
        'status' => 'Status',
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
    ],

    'edit' => [
        'title' => 'Edit Tenant: :name',
        'submit' => 'Save Changes',
    ],

    'sections' => [
        'tenant' => 'Tenant',
        'modules' => 'Modules',
        'admin' => 'First admin',
        'payments' => 'Payments',
        'admin_description' => 'Optional. They\'re made a Super Admin and emailed a link to set their password.',
    ],

    'fields' => [
        'name' => 'Name',
        'name_help' => 'A short handle: lowercase letters, numbers and dashes, e.g. acme-ltd.',
        'domain' => 'Domain',
        'domain_help' => 'The tenant\'s first domain, e.g. acme.example.com, or just acme for a subdomain of this admin\'s domain. More can be added later.',
        'industry' => 'Industry',
        'industry_help' => 'What kind of business this is. It sets the words and defaults the tenant starts with.',
        'modules_help' => 'Which optional modules the tenant can use.',
        'modules_suggested' => 'Suggested for this industry: :modules.',
        'platform_fee_percent' => 'Platform fee (%)',
        'platform_fee_fixed' => 'Platform fee (GBP per payment)',
        'platform_fee_help' => 'Taken from each payment the tenant takes online, on top of Stripe\'s own fees. Leave both blank to take nothing.',
        'stripe_connected' => 'Their Stripe account is connected and can take payments.',
        'stripe_not_connected' => 'They haven\'t connected a Stripe account that can take payments yet.',
        'admin_first_name' => 'First Name',
        'admin_last_name' => 'Last Name',
        'admin_email' => 'Email',
    ],

    'status' => [
        'active' => 'Active',
        'disabled' => 'Disabled',
    ],

    'setup' => [
        'setting_up_title' => 'Setting up :name',
        'setting_up_description' => 'Creating its database and getting it ready. This usually takes under a minute, and carries on if you leave this page.',
        'setting_up_with_admin' => 'Once it\'s ready, :email will be emailed a link to set their password.',
        'stalled' => 'This is taking longer than usual. Check that the queue worker is running.',
        'failed_title' => 'Setting up :name failed',
        'failed_description' => 'Its half-made database has been removed. Try again, or delete the tenant.',
        'retry' => 'Try again',
        'ready' => ':name is ready.',
        'unknown_error' => 'Something went wrong while setting it up.',
        'no_domain_for_invite' => 'The first admin can\'t be invited, as the tenant has no full domain to send them to.',
    ],

    'show' => [
        'title' => 'Tenant: :name',
        'back' => 'Tenants',
        'details' => 'Details',
        'domains' => 'Domains',
        'modules' => 'Modules',
        'no_modules' => 'No modules are switched on.',
        'open_admin' => 'Open admin',
    ],

    'domains' => [
        'add' => 'Add domain',
        'placeholder' => 'acme.example.com, or acme',
        'help' => 'A full domain, or just a name (e.g. acme) for a subdomain of this admin\'s domain. Its DNS (and SSL) must point at this app before the site answers on it.',
        'edit' => 'Edit domain',
        'remove' => 'Remove domain',
        'confirm_remove' => 'Remove :domain? The site will stop answering on it.',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'flash' => [
            'added' => 'Domain :domain added.',
            'updated' => 'Domain changed to :domain.',
            'removed' => 'Domain :domain removed.',
        ],
        'validation' => [
            'last_domain' => 'A tenant needs at least one domain. Add another before removing this one.',
        ],
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
        'created' => 'Tenant :name created. It\'s being set up now.',
        'retrying' => 'Setting up :name again.',
        'retry_not_failed' => ':name\'s setup hasn\'t failed, so there\'s nothing to try again.',
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

    // Shown by a tenant's site, admin and API while it's being set up
    'setting_up_message' => 'This site is being set up. Try again in a minute.',
];

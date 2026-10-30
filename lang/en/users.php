<?php

/*
|--------------------------------------------------------------------------
| Users Language Lines
|--------------------------------------------------------------------------
|
| Strings for the tenant users module.
|
*/

return [
    'fields' => [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'role' => 'Role',
        'roles' => 'Roles',
    ],

    'columns' => [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'roles' => 'Roles',
    ],

    'index' => [
        'title' => 'Users',
        'create' => 'New user',
        'empty' => 'No users yet.',
    ],

    'create' => [
        'title' => 'Create User',
        'submit' => 'Create User',
        'password_email_notice' => 'A password setup email will be sent to the user so they can set their own password.',
    ],

    'edit' => [
        'title' => 'Edit User: :name',
        'submit' => 'Save Changes',
    ],

    'show' => [
        'title' => 'User: :name',
    ],

    'validation' => [
        'first_name_required' => 'First name is required',
        'last_name_required' => 'Last name is required',
        'email_invalid' => 'Invalid email address',
    ],

    'confirm_delete' => 'Are you sure you want to delete user :email?',

    // The central admin's users, who are all super users
    'central' => [
        'search' => 'Search name or email',
        'you' => 'You',
        'invite_notice' => 'They\'ll be emailed a link to set their password, and can then manage everything in the central admin.',
        'validation' => [
            'delete_self' => 'You can\'t delete yourself here. Use your profile settings instead.',
            'last_user' => 'You\'re the only central admin user, so your account can\'t be deleted.',
        ],
    ],

    'flash' => [
        'created' => 'User created. A password setup email has been sent to :email.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User deleted successfully.',
    ],
];

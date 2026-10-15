<?php

/*
|--------------------------------------------------------------------------
| Settings Language Lines
|--------------------------------------------------------------------------
|
| Strings for the account settings pages (profile, security, deleting an account).
|
*/

return [
    'title' => 'Settings',
    'success' => 'Success',
    'save_changes' => 'Save changes',

    'profile' => [
        'nav' => 'Profile',
        'head' => 'Profile settings',
        'title' => 'Profile',
        'description' => 'This information will be displayed publicly.',
        'updated' => 'Your profile has been updated.',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'email_description' => 'Used to sign in, for email receipts and product updates.',
        'unverified' => 'Your email address is unverified.',
        'resend_verification' => 'Click here to resend the verification email.',
        'verification_sent' => 'A new verification link has been sent to your email address.',
        'timezone' => 'Timezone',
        'timezone_description' => 'Decides when your tracker days start and end.',
        'validation' => [
            'too_short' => 'Too short',
            'too_long' => 'Too long',
            'timezone_required' => 'Timezone is required',
        ],
    ],

    'security' => [
        'nav' => 'Security',
        'head' => 'Security settings',
        'password_title' => 'Password',
        'password_description' => 'Confirm your current password before setting a new one.',
        'password_updated' => 'Your password has been updated.',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm password',
        'update' => 'Update',
        'account_title' => 'Account',
        'account_description' => 'No longer want to use our service? You can delete your account here. This action is not reversible. All information related to this account will be deleted permanently.',
    ],

    'delete_account' => [
        'title' => 'Are you sure you want to delete your account?',
        'description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
        'open' => 'Delete account',
        'password' => 'Password',
        'submit' => 'Delete Account',
    ],
];

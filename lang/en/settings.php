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

    'profile' => [
        'nav' => 'Profile',
        'title' => 'Profile',
        'description' => 'This information will be displayed publicly.',
        'updated' => 'Your profile has been updated.',
        'name' => 'Name',
        'name_description' => 'How you appear in the admin.',
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
        'title' => 'Security',
        'description' => 'Your password and account.',
        'password_title' => 'Password',
        'password_description' => 'Confirm your current password before setting a new one.',
        'password_updated' => 'Your password has been updated.',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm password',
        'update' => 'Update password',
        'account_title' => 'Account',
        'account_description' => 'No longer want to use our service? You can delete your account here. This action is not reversible. All information related to this account will be deleted permanently.',
    ],

    'two_factor' => [
        'title' => 'Two-factor login',
        'description' => 'Ask for a code from an authenticator app as well as your password when you log in.',
        'on' => 'On',
        'off' => 'Off',
        'turn_on' => 'Turn on',
        'turn_off' => 'Turn off',
        'turned_on' => 'Two-factor login is on.',
        'turned_off' => 'Two-factor login is off.',
        'failed' => 'Something went wrong. Reload the page and try again.',
        'setup_title' => 'Set up two-factor login',
        'setup_description' => 'Scan the QR code with an authenticator app, such as 1Password, Google Authenticator or Authy, then enter the 6-digit code it shows.',
        'qr_code' => 'QR code for your authenticator app',
        'setup_key' => 'Or enter this key',
        'code' => 'Code from your app',
        'confirm' => 'Turn on',
        'show_recovery_codes' => 'Show recovery codes',
        'hide_recovery_codes' => 'Hide recovery codes',
        'regenerate_recovery_codes' => 'Make new codes',
        'recovery_codes_regenerated' => 'New recovery codes made. The old ones no longer work.',
        'recovery_codes_description' => 'If you lose your phone, log in with one of these instead of a code. Each works once. Keep them somewhere safe, like a password manager.',
        'copy' => 'Copy',
        'copied' => 'Copied',
    ],

    'delete_account' => [
        'title' => 'Are you sure you want to delete your account?',
        'description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
        'open' => 'Delete account',
        'password' => 'Password',
        'submit' => 'Delete Account',
    ],
];

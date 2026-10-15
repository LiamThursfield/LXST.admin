<?php

/*
|--------------------------------------------------------------------------
| Authentication Page Language Lines
|--------------------------------------------------------------------------
|
| Strings for the login, registration, password and verification pages.
| These are merged with Laravel's own auth lines (e.g. auth.failed).
|
*/

return [
    'fields' => [
        'email' => 'Email address',
        'email_placeholder' => 'email@example.com',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'remember_me' => 'Remember me',
    ],

    'login' => [
        'title' => 'Sign In',
        'description' => 'Welcome back to your account.',
        'head' => 'Sign in',
        'forgot_password' => 'Forgot password?',
        'submit' => 'Log in',
        'no_account' => "Don't have an account?",
        'sign_up' => 'Sign up',
    ],

    'register' => [
        'title' => 'Sign Up',
        'description' => 'Create a new account.',
        'head' => 'Register',
        'submit' => 'Create Account',
        'have_account' => 'Already have an account?',
        'log_in' => 'Log In',
    ],

    'forgot_password' => [
        'title' => 'Forgot Password',
        'description' => 'Request a password reset link.',
        'head' => 'Forgot Password',
        'submit' => 'Reset Password',
        'return_to' => 'Or, return to',
        'log_in' => 'log in',
    ],

    'reset_password' => [
        'title' => 'Reset Password',
        'description' => 'Please enter your new password below.',
        'head' => 'Reset Password',
        'submit' => 'Reset Password',
        'return_to' => 'Or, return to',
        'log_in' => 'Log In',
    ],

    'confirm_password' => [
        'title' => 'Confirm your password',
        'description' => 'This is a secure area of the application. Please confirm your password before continuing.',
        'head' => 'Confirm password',
        'submit' => 'Confirm Password',
    ],

    'verify_email' => [
        'title' => 'Verify email',
        'description' => 'Please verify your email address by clicking on the link we just emailed to you.',
        'head' => 'Verify email',
        'link_sent' => 'A new verification link has been sent to the email address you provided during registration.',
        'resend' => 'Resend verification email',
        'log_out' => 'Log out',
    ],
];

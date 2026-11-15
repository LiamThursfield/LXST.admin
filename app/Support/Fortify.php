<?php

namespace App\Support;

use Laravel\Fortify\Fortify as BaseFortify;

/**
 * Fortify, plus the app's own auth switches. Use in place of Laravel\Fortify\Fortify
 */
class Fortify extends BaseFortify
{
    /**
     * Whether people can create their own account from a site's sign-up page
     */
    public static function registrationOpen(): bool
    {
        return (bool) config('fortify.registration_open');
    }
}

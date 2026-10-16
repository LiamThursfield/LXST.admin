<?php

namespace App\Models\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * A tenant's general settings, stored in the tenant database's `settings` table
 */
class GeneralSettings extends Settings
{
    public string $site_name;

    public static function group(): string
    {
        return 'general';
    }
}

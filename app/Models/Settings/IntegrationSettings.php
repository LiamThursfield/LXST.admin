<?php

namespace App\Models\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * A tenant's third-party integrations, stored in the tenant database's `settings` table
 */
class IntegrationSettings extends Settings
{
    /** The Google Tag Manager container ID, e.g. GTM-ABC1234 */
    public ?string $gtm_id;

    public static function group(): string
    {
        return 'integrations';
    }
}

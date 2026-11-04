<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Makes a tenant with no industry yet into the seeded business (see SiteSettingsSeeder): a mobile
 * DJ, who says "DJ" rather than the industry's "performer". A tenant already in an industry keeps
 * it, along with its own words.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=IndustrySeeder`.
 * Safe to re-run: the second run finds the industry already set.
 */
class IndustrySeeder extends Seeder
{
    public const string INDUSTRY = 'events';

    public function run(): void
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || $tenant->industry !== config('industries.fallback')) {
            return;
        }

        $tenant->update([
            'industry' => self::INDUSTRY,
            'industry_overrides' => [
                'terms' => [
                    'staff_member' => ['singular' => 'DJ', 'plural' => 'DJs'],
                ],
            ],
        ]);
    }
}

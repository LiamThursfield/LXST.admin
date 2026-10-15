<?php

namespace Database\Seeders;

use App\Enums\Crm\SocialMedia;
use App\Models\Crm\Address;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Seeder;

/**
 * Seeds example CRM data: a company with two locations, each with a couple of departments.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=CrmSeeder`.
 * Safe to re-run: nothing is seeded if organisation units already exist.
 */
class CrmSeeder extends Seeder
{
    public function run(): void
    {
        if (OrganisationUnit::query()->exists()) {
            return;
        }

        $company = OrganisationUnit::factory()->company()->create([
            'name' => 'Acme Ltd',
            'socials' => [
                SocialMedia::LinkedIn->value => 'https://www.linkedin.com/company/acme',
                SocialMedia::Instagram->value => 'https://www.instagram.com/acme',
            ],
        ]);
        Address::factory()->for($company, 'addressable')->create();

        foreach (['Manchester', 'Leeds'] as $city) {
            $location = OrganisationUnit::factory()->location($company)->create(['name' => "{$city} Office"]);
            Address::factory()->for($location, 'addressable')->create(['town_city' => $city]);

            foreach (['Sales', 'Support'] as $department) {
                OrganisationUnit::factory()->department($location)->create(['name' => "{$city} {$department}"]);
            }
        }
    }
}

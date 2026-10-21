<?php

namespace Database\Seeders;

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;

/**
 * Seeds a whole tenant with realistic data to test every feature (#164): roles, a known user per
 * role, more users, and each module the tenant has. Run within a tenant, e.g.
 * `php artisan tenants:refresh {tenant} --seed`, or `php artisan tenants:seed`.
 *
 * Every seeder it calls is safe to re-run, so running it again creates nothing new.
 */
class TenantDatabaseSeeder extends Seeder
{
    /** The same seed every run, so the same data is made every time */
    protected const int RANDOM_SEED = 2026;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        fake()->seed(self::RANDOM_SEED);

        $this->call([
            RolesAndPermissionsSeeder::class,
            RoleUserSeeder::class,
            UserSeeder::class,
        ]);

        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        if ($tenant->hasModule(TenantModule::Crm)) {
            $this->call(CrmSeeder::class);
        }

        // After the CRM, as pages can show its forms and offices
        if ($tenant->hasModule(TenantModule::Cms)) {
            $this->call(CmsSeeder::class);
        }

        if ($tenant->hasModule(TenantModule::Tracker)) {
            // Tracker data belongs to a user: the admin, so it's there when signing in as them
            $this->callWith(TrackerSeeder::class, ['user' => RoleUserSeeder::userFor(RoleEnum::Admin)]);
        }

        if ($tenant->hasModule(TenantModule::Dj)) {
            $this->call(DjSeeder::class);
        }

        // The File Manager and settings (#168) seeders are added here as they're built
    }
}

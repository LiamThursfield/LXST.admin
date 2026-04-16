<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync {--tenant= : The ID of the tenant to sync permissions for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync roles and permissions across all tenants or a specific one';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenant = Tenant::findOrFail($tenantId);
            $this->syncForTenant($tenant);
        } else {
            Tenant::all()->each(function ($tenant) {
                /** @var Tenant $tenant - required for phpstan */
                $this->syncForTenant($tenant);
            });
        }

        $this->info('Permissions sync completed.');

        return Command::SUCCESS;
    }

    /**
     * Sync permissions for a single tenant.
     */
    protected function syncForTenant(Tenant $tenant): void
    {
        $this->info("Syncing permissions for tenant: {$tenant->getTenantKey()}");

        $tenant->run(function () {
            (new RolesAndPermissionsSeeder)->run();
        });
    }
}

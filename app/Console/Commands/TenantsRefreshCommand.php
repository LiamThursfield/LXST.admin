<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\RoleUserSeeder;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Resets a tenant to a fresh database, optionally seeded with realistic data to test every feature
 * (#164), signing in afterwards as one of the role users. For local testing only.
 */
class TenantsRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:refresh
        {tenant : The ID of the tenant to reset}
        {--seed : Seed it with test data afterwards}
        {--clear-storage : Also delete its stored files (File Manager uploads and library imports)}
        {--force : Run outside the local environment, and without asking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop a tenant\'s tables, migrate it fresh and (with --seed) seed it with test data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenantId = is_string($tenantId) ? $tenantId : '';
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            $this->error("No tenant with the ID {$tenantId}.");

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        if (! app()->isLocal() && ! $force) {
            $this->error('This deletes all of the tenant\'s data, so it only runs locally. Use --force to run it anyway.');

            return self::FAILURE;
        }

        if (! $force && ! $this->confirm("Delete all of tenant {$tenant->getTenantKey()}'s data and start again?")) {
            $this->info('Nothing was changed.');

            return self::SUCCESS;
        }

        $this->call('tenants:migrate-fresh', ['--tenants' => [$tenant->getTenantKey()], '--force' => true]);

        if ($this->option('clear-storage')) {
            $tenant->run(fn () => $this->clearStorage());
            $this->info('Stored files deleted.');
        }

        if ($this->option('seed')) {
            $this->call('tenants:seed', [
                '--tenants' => [$tenant->getTenantKey()],
                '--class' => TenantDatabaseSeeder::class,
                '--force' => true,
            ]);

            $this->newLine();
            $this->info('Sign in as any of these (passwords are in Database\Seeders\RoleUserSeeder):');
            $this->table(['Role', 'Email'], collect(RoleUserSeeder::USERS)
                ->map(fn (array $user, string $role) => [$role, $user['email']])
                ->values()
                ->all());
        }

        return self::SUCCESS;
    }

    /**
     * Files the fresh database no longer has records for. Run within the tenant, so each disk is
     * the tenant's own folder (or S3 prefix).
     */
    protected function clearStorage(): void
    {
        $fileManager = Storage::disk((string) config('file-manager.disk'));

        foreach ($fileManager->directories() as $directory) {
            $fileManager->deleteDirectory($directory);
        }

        $fileManager->delete($fileManager->files());

        Storage::disk((string) config('dj.imports.disk'))->deleteDirectory('dj-imports');
    }
}

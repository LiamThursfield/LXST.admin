<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Tenant;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\URL;

/**
 * This Trait was taken/adapted from previous work by https://github.com/sdsmith1981
 * It assists with setting up a test tenant, ensuring parallel tests work as expected, and improving test performance
 */
trait RefreshDatabaseWithTenantTrait
{
    use RefreshDatabase {
        RefreshDatabase::beginDatabaseTransaction as parentBeginDatabaseTransaction;
    }

    /**
     * The database connections that should have transactions.
     *
     * `null` is the default landlord connection, used for system-wide operations.
     * `tenant` is the tenant connection, specific to each tenant in the multi-tenant system.
     */
    protected array $connectionsToTransact = [null, 'tenant'];

    /**
     * We need to hook initialize tenancy _before_ we start the database
     * transaction, otherwise it cannot find the tenant connection.
     * This function initializes the tenant setup before starting a transaction.
     */
    public function beginDatabaseTransaction(): void
    {
        // Initialize tenant before beginning the database transaction.
        $this->initializeTenant();

        // Continue with the default database transaction setup.
        $this->parentBeginDatabaseTransaction();
    }

    /**
     * Initialize tenant for testing environment.
     * This function sets up a specific tenant for testing purposes.
     */
    public function initializeTenant(): void
    {
        // Hardcoded tenant ID for testing purposes.
        $tenantId = 'test_tenant';

        // Retrieve or create the tenant with the given ID.
        $tenant = Tenant::query()->firstOr(function () use ($tenantId) {

            /*
             * Set the tenant prefix to the parallel testing token.
             * This is necessary to avoid database collisions when running tests in parallel.
             */
            if (ParallelTesting::token()) {
                config(['tenancy.database.prefix' => config('tenancy.database.prefix').ParallelTesting::token().'_']);
            }

            // Define the database name for the tenant.
            $dbName = config('tenancy.database.prefix').$tenantId;
            // Drop the database if it already exists.
            DB::unprepared(sprintf('DROP DATABASE IF EXISTS `%s`', $dbName));

            // Create the tenant and associated domain if they don't exist.
            try {
                $t = Tenant::query()->create([
                    'id' => $tenantId,
                    'name' => 'Test Tenant',
                ]);
            } catch (Exception $exception) {
                // If creation failed (e.g. database already exists), find the existing tenant
                Log::error('Error creating tenant: '.$exception->getMessage());
                $t = Tenant::query()->find($tenantId);

                throw_unless($t, $exception);
            }

            // Artisan::call('tenants:migrate', ['--tenants' => $tenantId]);

            if (! $t->domains()->count()) {
                $t->domains()->create(['domain' => 'tenant.test']);
            }

            return $t;
        });

        // Initialize tenancy for the current test.
        tenancy()->initialize($tenant);

        // Set the root URL for the current tenant.
        URL::useOrigin('http://tenant.test');
    }
}

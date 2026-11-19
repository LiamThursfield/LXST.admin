<?php

namespace App\Services\Central;

use App\Jobs\SeedRolesAndPermissions;
use App\Models\Tenant;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

/**
 * A tenant's own database: creating, migrating and seeding it, and deleting it. Tenants made by
 * commands (e.g. `central:make:tenant`) get theirs as they're saved (TenancyServiceProvider);
 * those made from the central admin get theirs in the background (ProvisionTenant).
 */
class TenantDatabase
{
    /**
     * The steps, in order, run with the tenant. A step that returns false stops the rest
     * (CreateDatabase does, for a tenant that mustn't have a database made).
     *
     * @var list<class-string>
     */
    protected const array STEPS = [
        CreateDatabase::class,
        MigrateDatabase::class,
        SeedRolesAndPermissions::class,
    ];

    public function create(Tenant $tenant): void
    {
        foreach (self::STEPS as $step) {
            if (app()->call([new $step($tenant), 'handle']) === false) {
                return;
            }
        }
    }

    public function exists(Tenant $tenant): bool
    {
        return $tenant->database()->manager()->databaseExists($tenant->database()->getName());
    }

    /**
     * Deletes the database, if it has one. A tenant whose setup failed doesn't.
     */
    public function delete(Tenant $tenant): void
    {
        if ($this->exists($tenant)) {
            app()->call([new DeleteDatabase($tenant), 'handle']);
        }
    }
}

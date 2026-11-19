<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Central\TenantProvisioner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sets up a tenant made from the central admin: its database, and inviting its first admin.
 * Creating and migrating a database takes a while, so it's done here rather than while the
 * form is submitted, and the tenant's page shows how it's going.
 */
class ProvisionTenant implements ShouldQueue
{
    use Queueable;

    /** A failed setup is cleaned up and tried again from the tenant's page, not retried */
    public int $tries = 1;

    /** Under the queue worker's own timeout (300s on the server), so this one is reached first */
    public int $timeout = 240;

    /** A tenant deleted before its setup ran has nothing left to set up */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Tenant $tenant) {}

    public function handle(TenantProvisioner $provisioner): void
    {
        $provisioner->setUp($this->tenant);
    }

    public function failed(?Throwable $exception): void
    {
        app(TenantProvisioner::class)->setUpFailed($this->tenant, $exception);
    }
}

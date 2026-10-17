<?php

namespace App\Services\Central;

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorisation\Enums\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a tenant from the central admin: the tenant (whose database the tenancy pipeline creates,
 * migrates and seeds, as `central:make:tenant` does), its first domain and, optionally, its first
 * admin, who gets the usual password setup email. If any step fails, what was made is removed.
 */
class TenantProvisioner
{
    public function __construct(protected TenantUrls $urls) {}

    /**
     * @param  Collection<int, TenantModule>  $modules
     * @param  array{first_name: string, last_name: string, email: string}|null  $admin
     *
     * @throws Throwable when provisioning fails, after cleaning up
     */
    public function provision(string $name, string $domain, Collection $modules, ?array $admin = null): Tenant
    {
        // Made before saving, so it can be cleaned up when the database pipeline (which runs while
        // it saves) fails part way
        $tenant = new Tenant(['name' => $name, 'modules' => $modules]);

        try {
            $tenant->save();
            $tenant->domains()->create(['domain' => $domain]);

            if ($admin !== null) {
                $this->inviteAdmin($tenant, $domain, $admin);
            }

            return $tenant;
        } catch (Throwable $exception) {
            if ($tenant->exists) {
                $this->cleanUp($tenant);
            }

            throw $exception;
        }
    }

    /**
     * Creates the tenant's first user as a Super Admin, and emails them a link to set their
     * password on the tenant's own domain
     *
     * @param  array{first_name: string, last_name: string, email: string}  $admin
     */
    protected function inviteAdmin(Tenant $tenant, string $domain, array $admin): void
    {
        $tenant->run(function () use ($admin, $domain) {
            $user = User::query()->create([...$admin, 'password' => Str::password()]);
            $user->assignRole(Role::SuperAdmin->value);

            URL::forceRootUrl($this->urls->root($domain));

            try {
                Password::sendResetLink(['email' => $user->email]);
            } finally {
                URL::forceRootUrl(null);
            }
        });
    }

    /**
     * Removes a half-made tenant: deleting it also deletes its domains and database
     */
    protected function cleanUp(Tenant $tenant): void
    {
        try {
            $tenant->delete();
        } catch (Throwable $exception) {
            // e.g. its database was never created, so can't be deleted
            report($exception);
        }
    }
}

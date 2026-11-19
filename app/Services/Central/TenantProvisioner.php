<?php

namespace App\Services\Central;

use App\Enums\TenantModule;
use App\Enums\TenantSetupStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorisation\Enums\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Stancl\Tenancy\Database\Exceptions\TenantDatabaseAlreadyExistsException;
use Throwable;

/**
 * Creates a tenant from the central admin. The tenant and its first domain are saved straight
 * away (so the domain is taken, and the tenant has a page to show how it's going), then its
 * database is created, migrated and seeded, and its first admin invited, in the background
 * (ProvisionTenant). If that fails, what was made is removed so it can be tried again.
 */
class TenantProvisioner
{
    public function __construct(protected TenantUrls $urls, protected TenantDatabase $database) {}

    /**
     * @param  Collection<int, TenantModule>  $modules
     * @param  array{first_name: string, last_name: string, email: string}|null  $admin
     */
    public function provision(string $name, string $domain, Collection $modules, ?array $admin = null, ?string $industry = null): Tenant
    {
        $tenant = DB::transaction(function () use ($name, $domain, $modules, $admin, $industry) {
            $tenant = new Tenant(['name' => $name, 'modules' => $modules]);
            $tenant->industry = $industry ?? $tenant->industry;
            $tenant->setup_status = TenantSetupStatus::SettingUp;
            $tenant->setup_admin = $admin;
            $tenant->save();

            $tenant->domains()->create(['domain' => $domain]);

            return $tenant;
        });

        ProvisionTenant::dispatch($tenant);

        return $tenant;
    }

    /**
     * Tries setting up a tenant whose setup failed again
     */
    public function retry(Tenant $tenant): void
    {
        $tenant->update(['setup_status' => TenantSetupStatus::SettingUp, 'setup_error' => null]);

        ProvisionTenant::dispatch($tenant);
    }

    /**
     * Creates, migrates and seeds the tenant's database, and invites its first admin (run by
     * ProvisionTenant)
     */
    public function setUp(Tenant $tenant): void
    {
        $this->database->create($tenant);

        if ($tenant->setup_admin !== null) {
            $this->inviteAdmin($tenant, $tenant->setup_admin);
        }

        $tenant->update(['setup_status' => TenantSetupStatus::Ready, 'setup_error' => null, 'setup_admin' => null]);
    }

    /**
     * Removes the half-made database, so the setup can be tried again, and keeps why it failed
     * to show on the tenant's page. The admin's details are kept for the next try.
     */
    public function setUpFailed(Tenant $tenant, ?Throwable $exception): void
    {
        // A database that was already there wasn't made by this setup, so isn't removed
        if (! $exception instanceof TenantDatabaseAlreadyExistsException) {
            try {
                $this->database->delete($tenant);
            } catch (Throwable $deleteException) {
                report($deleteException);
            }
        }

        $tenant->update([
            'setup_status' => TenantSetupStatus::Failed,
            'setup_error' => filled($exception?->getMessage()) ? $exception->getMessage() : __('tenants.setup.unknown_error'),
        ]);
    }

    /**
     * Creates the tenant's first user as a Super Admin, and emails them a link to set their
     * password on the tenant's own domain
     *
     * @param  array{first_name: string, last_name: string, email: string}  $admin
     */
    protected function inviteAdmin(Tenant $tenant, array $admin): void
    {
        $domain = $this->urls->primaryDomain($tenant)
            ?? throw new RuntimeException(__('tenants.setup.no_domain_for_invite'));

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
}

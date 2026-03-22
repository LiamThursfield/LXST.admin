<?php

namespace App\Console\Commands\Central;

use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

#[Signature('central:make:tenant')]
#[Description('Makes a tenant')]
class MakeTenant extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $name = $this->ask('What is the tenant name?');
        if ($name == null || Str::length($name) === 0) {
            $this->error('Tenant name is required.');

            return;
        }

        $name = Str::slug($name);
        if (Tenant::query()->where('name', $name)->exists()) {
            $this->error('Tenant already exists.');

            return;
        }

        $this->info('Creating the tenant database, this may take a while.');
        $tenant = Tenant::query()->create(['name' => $name]);

        $domainCount = 0;
        do {
            $domain = $this->ask('Add a domain? (leave blank to skip)');
            if ($domain) {
                if ($this->addDomain($tenant, $domain)) {
                    $domainCount++;
                }
            } elseif ($domainCount < 1) {
                $this->error('At least one domain is required.');
            }
        } while ($domain || $domainCount < 1);

        $this->comment('Tenant Created: '.$tenant->id.' with '.$domainCount.' domains.');
    }

    protected function addDomain(Tenant $tenant, string $domain): bool
    {
        try {
            $tenant->domains()->create(['domain' => $domain]);
            return true;
        } catch (DomainOccupiedByOtherTenantException) {
            $this->error('Domain is already occupied.');
            return false;
        }
    }
}

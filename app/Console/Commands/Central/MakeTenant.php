<?php

namespace App\Console\Commands\Central;

use App\Enums\TenantModule;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

use function Laravel\Prompts\multiselect;

#[Signature('central:make:tenant {--modules= : Comma separated modules to switch on (cms, crm, tracker). Prompts when omitted}')]
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

        $modules = $this->getModules();
        if ($modules === null) {
            return;
        }

        $this->info('Creating the tenant database, this may take a while.');
        $tenant = Tenant::query()->create(['name' => $name, 'modules' => $modules]);

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

        $moduleList = $modules->isEmpty() ? 'no modules' : 'modules: '.$modules->map(fn (TenantModule $module) => $module->value)->implode(', ');
        $this->comment('Tenant Created: '.$tenant->id.' with '.$domainCount.' domains and '.$moduleList.'.');
    }

    /**
     * Returns the modules to switch on, from the --modules option or a prompt
     * Returns null (after showing an error) if the option contains an unknown module
     *
     * @return Collection<int, TenantModule>|null
     */
    protected function getModules(): ?Collection
    {
        $option = $this->option('modules');

        if (! is_string($option)) {
            $selected = multiselect(
                label: 'Which modules should the tenant have?',
                options: collect(TenantModule::cases())
                    ->mapWithKeys(fn (TenantModule $module) => [$module->value => $module->label()])
                    ->all(),
                default: array_map(fn (TenantModule $module) => $module->value, TenantModule::cases()),
            );

            return collect($selected)->map(fn (int|string $value) => TenantModule::from((string) $value));
        }

        $values = Str::of($option)->explode(',')->map(fn (string $value) => trim($value))->filter();
        $unknown = $values->reject(fn (string $value) => TenantModule::tryFrom($value) !== null);

        if ($unknown->isNotEmpty()) {
            $this->error('Unknown module(s): '.$unknown->implode(', ').'. Valid modules: '
                .implode(', ', array_map(fn (TenantModule $module) => $module->value, TenantModule::cases())).'.');

            return null;
        }

        return $values->unique()->map(fn (string $value) => TenantModule::from($value))->values();
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

<?php

namespace App\Http\Requests\Central\Admin\Concerns;

use App\Enums\TenantModule;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The rules shared by creating and editing a tenant
 */
trait ValidatesTenant
{
    /**
     * @return array<string, mixed>
     */
    protected function tenantRules(?Tenant $tenant = null): array
    {
        return [
            // A short handle for the tenant, as `central:make:tenant` makes (e.g. acme-ltd)
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'name')->ignore($tenant?->getTenantKey())],
            'modules' => ['nullable', 'array'],
            'modules.*' => [Rule::enum(TenantModule::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function tenantMessages(): array
    {
        return [
            'name.regex' => __('tenants.validation.name_format'),
        ];
    }

    /**
     * @return Collection<int, TenantModule>
     */
    public function modules(): Collection
    {
        /** @var list<string> $values */
        $values = $this->validated('modules') ?? [];

        // In the enum's order, so the list reads the same however it was ticked
        /** @var Collection<int, TenantModule> $modules */
        $modules = collect(TenantModule::cases())
            ->filter(fn (TenantModule $module) => in_array($module->value, $values, true))
            ->values();

        return $modules;
    }
}

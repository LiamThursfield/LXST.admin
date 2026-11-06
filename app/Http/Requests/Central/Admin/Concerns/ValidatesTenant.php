<?php

namespace App\Http\Requests\Central\Admin\Concerns;

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Services\Industry\IndustryProfiles;
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
            'industry' => ['sometimes', 'required', 'string', Rule::in(app(IndustryProfiles::class)->keys())],
            'modules' => ['nullable', 'array'],
            'modules.*' => [Rule::enum(TenantModule::class)],
            // What the platform takes from each payment the tenant takes online (#180)
            'platform_fee_percent' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:50'],
            'platform_fee_fixed' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
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

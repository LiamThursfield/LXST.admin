<?php

namespace App\Http\Resources\Central\Admin;

use App\Enums\TenantModule;
use App\Http\Resources\Traits\HasActions;
use App\Models\Tenant;
use App\Services\Central\TenantUrls;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Stancl\Tenancy\Contracts\Domain;

class TenantResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;

        return [
            Action::make(__('tenants.actions.view'), route('central.admin.tenants.show', $tenant))
                ->icon('i-lucide-eye'),
            Action::make(__('common.actions.edit'), route('central.admin.tenants.edit', $tenant))
                ->icon('i-lucide-pencil'),
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource;
        $modules = $tenant->modules ?? collect();
        $urls = app(TenantUrls::class);
        $primaryDomain = $urls->primaryDomain($tenant);

        return $this->appendActions($request, [
            'id' => $tenant->getTenantKey(),
            'name' => $tenant->name,
            'domains' => $tenant->domains
                ->sortBy(fn (Domain $domain) => $domain->getKey())
                ->map(fn (Domain $domain) => [
                    'id' => (int) $domain->getKey(),
                    'domain' => (string) $domain->getAttribute('domain'),
                    'admin_url' => $urls->admin((string) $domain->getAttribute('domain')),
                ])
                ->values()
                ->all(),
            'admin_url' => $primaryDomain === null ? null : $urls->admin($primaryDomain),
            'modules' => collect(TenantModule::cases())
                ->filter(fn (TenantModule $module) => $modules->contains($module))
                ->map(fn (TenantModule $module) => ['value' => $module->value, 'label' => $module->label()])
                ->values()
                ->all(),
            'is_disabled' => $tenant->isDisabled(),
            'disabled_at' => $tenant->disabled_at?->toIso8601String(),
            'created_at' => $tenant->created_at->toIso8601String(),
        ]);
    }
}

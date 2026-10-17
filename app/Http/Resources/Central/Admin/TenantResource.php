<?php

namespace App\Http\Resources\Central\Admin;

use App\Enums\TenantModule;
use App\Http\Resources\Traits\HasActions;
use App\Models\Tenant;
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

        return $this->appendActions($request, [
            'id' => $tenant->getTenantKey(),
            'name' => $tenant->name,
            'domains' => $tenant->domains->map(fn (Domain $domain) => [
                'domain' => $this->name($domain),
                // A bare subdomain like "acme" isn't reachable on its own
                'admin_url' => str_contains($this->name($domain), '.') ? $this->adminUrl($this->name($domain), $request) : null,
            ])->values()->all(),
            'admin_url' => $this->primaryAdminUrl($tenant, $request),
            'modules' => collect(TenantModule::cases())
                ->filter(fn (TenantModule $module) => $modules->contains($module))
                ->map(fn (TenantModule $module) => ['value' => $module->value, 'label' => $module->label()])
                ->values()
                ->all(),
            'created_at' => $tenant->created_at->toIso8601String(),
        ]);
    }

    /**
     * The tenant's admin, on its first full domain (one with a dot)
     */
    protected function primaryAdminUrl(Tenant $tenant, Request $request): ?string
    {
        /** @var Domain|null $domain */
        $domain = $tenant->domains->first(fn (Domain $domain) => str_contains($this->name($domain), '.'));

        return $domain === null ? null : $this->adminUrl($this->name($domain), $request);
    }

    protected function name(Domain $domain): string
    {
        return (string) $domain->getAttribute('domain');
    }

    /**
     * The admin on the given domain, using the central admin's scheme and port
     */
    protected function adminUrl(string $domain, Request $request): string
    {
        $port = in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort();

        return $request->getScheme().'://'.$domain.$port.'/admin';
    }
}

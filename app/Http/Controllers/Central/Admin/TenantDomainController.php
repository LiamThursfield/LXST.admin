<?php

namespace App\Http\Controllers\Central\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Admin\TenantDomainStoreRequest;
use App\Http\Requests\Central\Admin\TenantDomainUpdateRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * A tenant's domains, managed from its page in the central admin. Each domain still needs its DNS
 * (and SSL) pointing at this app before the site answers on it.
 */
class TenantDomainController extends Controller
{
    public function store(TenantDomainStoreRequest $request, Tenant $tenant): RedirectResponse
    {
        $domain = $tenant->domains()->create(['domain' => $request->validated('domain')]);

        return back()->with('success', __('tenants.domains.flash.added', ['domain' => $domain->getAttribute('domain')]));
    }

    public function update(TenantDomainUpdateRequest $request, Tenant $tenant, Domain $domain): RedirectResponse
    {
        $domain->update(['domain' => $request->validated('domain')]);

        return back()->with('success', __('tenants.domains.flash.updated', ['domain' => $domain->getAttribute('domain')]));
    }

    /**
     * A tenant can't be left without a domain, as it would be unreachable
     */
    public function destroy(Tenant $tenant, Domain $domain): RedirectResponse
    {
        if ($tenant->domains()->count() <= 1) {
            throw ValidationException::withMessages([
                'domain' => __('tenants.domains.validation.last_domain'),
            ]);
        }

        $domain->delete();

        return back()->with('success', __('tenants.domains.flash.removed', ['domain' => $domain->getAttribute('domain')]));
    }
}

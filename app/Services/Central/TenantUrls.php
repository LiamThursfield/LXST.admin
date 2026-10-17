<?php

namespace App\Services\Central;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\Domain;

/**
 * Links from the central admin to a tenant's own domains, using the central admin's scheme and
 * port (so they work locally, e.g. http://acme.localhost/admin)
 */
class TenantUrls
{
    public function __construct(protected Request $request) {}

    /**
     * Whether the domain can be reached on its own. A bare subdomain like "acme" can't.
     */
    public function isReachable(string $domain): bool
    {
        return str_contains($domain, '.');
    }

    /**
     * The root URL of the site on the given domain, e.g. https://acme.test
     */
    public function root(string $domain): string
    {
        $port = in_array($this->request->getPort(), [80, 443], true) ? '' : ':'.$this->request->getPort();

        return $this->request->getScheme().'://'.$domain.$port;
    }

    public function admin(string $domain): ?string
    {
        return $this->isReachable($domain) ? $this->root($domain).'/admin' : null;
    }

    /**
     * The tenant's first reachable domain, if it has one
     */
    public function primaryDomain(Tenant $tenant): ?string
    {
        return $tenant->domains
            ->map(fn (Domain $domain) => (string) $domain->getAttribute('domain'))
            ->first(fn (string $domain) => $this->isReachable($domain));
    }
}

<?php

namespace App\Http\Requests\Central\Admin\Concerns;

use Illuminate\Validation\Rule;

/**
 * The rules for a tenant's domain, shared by creating a tenant and managing its domains
 */
trait ValidatesDomain
{
    /**
     * The domain as typed, tidied: lowercase, and a bare name (e.g. "acme") expanded to a
     * subdomain of the central domain (e.g. "acme.admin.example.com"), as legacy allowed
     */
    protected function normaliseDomain(mixed $domain): string
    {
        $domain = strtolower(trim((string) $domain));
        /** @var list<string> $centralDomains */
        $centralDomains = config('tenancy.identification.central_domains');

        // Left as typed: full domains, anything invalid (for validation to explain), and the
        // central domain itself (which may have no dot, e.g. localhost)
        if ($domain === '' || str_contains($domain, '.') || ! preg_match('/^[a-z0-9-]+$/', $domain) || in_array($domain, $centralDomains, true)) {
            return $domain;
        }

        return $domain.'.'.$centralDomains[0];
    }

    /**
     * @return list<mixed>
     */
    protected function domainRules(?int $ignoreDomainId = null): array
    {
        return [
            'required',
            'string',
            'max:253',
            'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/',
            Rule::unique('domains', 'domain')->ignore($ignoreDomainId),
            Rule::notIn(config('tenancy.identification.central_domains')),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function domainMessages(): array
    {
        return [
            'domain.regex' => __('tenants.validation.domain_format'),
            'domain.unique' => __('tenants.validation.domain_taken'),
            'domain.not_in' => __('tenants.validation.domain_central'),
        ];
    }
}

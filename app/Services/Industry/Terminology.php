<?php

namespace App\Services\Industry;

use App\Models\Tenant;

/**
 * The words a tenant uses for things every business has, e.g. a customer is a guest, patient or
 * member. A term comes from, in order: the tenant's own override, its industry, then the general
 * word in lang/en/industries.php. Only overrides are stored, so a reworded default reaches every
 * tenant that hasn't changed that term.
 *
 * With no tenant (e.g. the central admin), the general words are used.
 */
class Terminology
{
    public function __construct(protected IndustryProfiles $profiles) {}

    /**
     * @return list<string>
     */
    public function termKeys(): array
    {
        return array_keys($this->baseTerms());
    }

    /**
     * The terms an industry starts with
     *
     * @return array<string, array{singular: string, plural: string}>
     */
    public function defaultsFor(string $industry): array
    {
        /** @var array<string, array{singular: string, plural: string}>|string $terms */
        $terms = __("industries.terminology.{$industry}");

        // A missing translation comes back as its key
        return [...$this->baseTerms(), ...(is_array($terms) ? $terms : [])];
    }

    /**
     * The terms a tenant uses, by default its current one
     *
     * @return array<string, array{singular: string, plural: string}>
     */
    public function forTenant(?Tenant $tenant = null): array
    {
        $tenant ??= $this->currentTenant();

        if ($tenant === null) {
            return $this->baseTerms();
        }

        return [...$this->defaultsFor($tenant->industryProfile()->key), ...$tenant->termOverrides()];
    }

    /**
     * The tenant's terms as translation placeholders, e.g. ['customer' => 'guest', 'customers' => 'guests']
     *
     * @return array<string, string>
     */
    public function placeholders(?Tenant $tenant = null): array
    {
        $placeholders = [];

        foreach ($this->forTenant($tenant) as $key => $term) {
            $placeholders[$key] = $term['singular'];
            $placeholders["{$key}s"] = $term['plural'];
        }

        return $placeholders;
    }

    /**
     * Translates a line, filling in the current tenant's terms
     *
     * @param  array<string, mixed>  $replace
     */
    public function translate(string $key, array $replace = []): string
    {
        return (string) __($key, [...$this->placeholders(), ...$replace]);
    }

    /**
     * @return array<string, array{singular: string, plural: string}>
     */
    protected function baseTerms(): array
    {
        /** @var array<string, array{singular: string, plural: string}> */
        return __('industries.terms');
    }

    protected function currentTenant(): ?Tenant
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}

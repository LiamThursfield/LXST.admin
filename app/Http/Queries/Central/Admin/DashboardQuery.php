<?php

namespace App\Http\Queries\Central\Admin;

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Services\Industry\IndustryProfile;
use App\Services\Industry\IndustryProfiles;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\Domain;

/**
 * What the central dashboard shows: tenant counts, the tenants that need attention, the newest
 * tenants, how many use each module and industry, and the state of the queue.
 *
 * Everything comes from the central database, so nothing here opens a tenant's own database.
 * Every tenant is loaded at once, which is fine for the number of tenants the platform hosts.
 */
class DashboardQuery
{
    /** How many of the newest tenants to list */
    protected const int RECENT_LIMIT = 5;

    /** A tenant created within this many days counts as new */
    protected const int NEW_TENANT_DAYS = 30;

    public function __construct(
        protected IndustryProfiles $industries,
        protected FailedJobProviderInterface $failedJobs,
    ) {}

    /**
     * @return array{
     *     stats: array{active: int, disabled: int, new: int, new_days: int},
     *     queue: array{pending: int, failed: int},
     *     attention: list<array{id: string, name: string, issues: list<string>, disabled_at: string|null}>,
     *     recent: list<array{id: string, name: string, industry: string, is_disabled: bool, created_at: string}>,
     *     modules: list<array{value: string, label: string, count: int}>,
     *     industries: list<array{value: string, label: string, count: int}>,
     * }
     */
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->with('domains')->latest()->get();
        $active = $tenants->reject(fn (Tenant $tenant) => $tenant->isDisabled());
        $disabled = $tenants->filter(fn (Tenant $tenant) => $tenant->isDisabled());

        return [
            'stats' => [
                'active' => $active->count(),
                'disabled' => $disabled->count(),
                'new' => $tenants->filter(fn (Tenant $tenant) => $tenant->created_at->gte($now->subDays(self::NEW_TENANT_DAYS)))->count(),
                'new_days' => self::NEW_TENANT_DAYS,
            ],
            'queue' => [
                'pending' => Queue::size(),
                'failed' => $this->failedJobCount(),
            ],
            'attention' => $this->attention($active, $disabled),
            'recent' => array_values($tenants->take(self::RECENT_LIMIT)
                ->map(fn (Tenant $tenant) => [
                    'id' => (string) $tenant->getTenantKey(),
                    'name' => $tenant->name,
                    'industry' => $tenant->industryProfile()->name(),
                    'is_disabled' => $tenant->isDisabled(),
                    'created_at' => $tenant->created_at->toIso8601String(),
                ])
                ->all()),
            'modules' => $this->moduleCounts($active),
            'industries' => $this->industryCounts($active),
        ];
    }

    /**
     * The active tenants with something to fix, by name, then the disabled ones, most recently
     * disabled first (a disabled tenant's other problems don't matter until it's switched back on)
     *
     * @param  Collection<int, Tenant>  $active
     * @param  Collection<int, Tenant>  $disabled
     * @return list<array{id: string, name: string, issues: list<string>, disabled_at: string|null}>
     */
    protected function attention(Collection $active, Collection $disabled): array
    {
        $activeWithIssues = $active
            ->map(fn (Tenant $tenant) => [
                'id' => (string) $tenant->getTenantKey(),
                'name' => $tenant->name,
                'issues' => $this->issues($tenant),
                'disabled_at' => null,
            ])
            ->filter(fn (array $item) => $item['issues'] !== [])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);

        $disabledItems = $disabled
            ->sortByDesc(fn (Tenant $tenant) => $tenant->disabled_at)
            ->map(fn (Tenant $tenant) => [
                'id' => (string) $tenant->getTenantKey(),
                'name' => $tenant->name,
                'issues' => ['disabled'],
                'disabled_at' => $tenant->disabled_at?->toIso8601String(),
            ]);

        return array_values($activeWithIssues->concat($disabledItems)->all());
    }

    /**
     * What's wrong with an active tenant, as keys of dashboard.issues
     *
     * @return list<string>
     */
    protected function issues(Tenant $tenant): array
    {
        $issues = [];

        // Only Bookings takes payments, so the rest don't need Stripe or a fee
        if ($tenant->hasModule(TenantModule::Bookings)) {
            if (! $tenant->canTakePayments()) {
                $issues[] = 'stripe';
            }

            if ($tenant->getAttribute('platform_fee_percent') === null && $tenant->getAttribute('platform_fee_fixed') === null) {
                $issues[] = 'no_fee';
            }
        }

        if (! $this->hasOwnDomain($tenant)) {
            $issues[] = 'no_own_domain';
        }

        return $issues;
    }

    /**
     * Whether any of the tenant's domains is its own, rather than a subdomain of the central domain
     */
    protected function hasOwnDomain(Tenant $tenant): bool
    {
        /** @var list<string> $centralDomains */
        $centralDomains = config('tenancy.identification.central_domains');

        return $tenant->domains->contains(function (Domain $domain) use ($centralDomains): bool {
            $name = (string) $domain->getAttribute('domain');

            // A bare name like "acme" is a subdomain of the central domain too
            return Str::contains($name, '.')
                && ! collect($centralDomains)->contains(fn (string $central) => $name === $central || Str::endsWith($name, '.'.$central));
        });
    }

    /**
     * How many active tenants have each module switched on, in the modules' own order
     *
     * @param  Collection<int, Tenant>  $active
     * @return list<array{value: string, label: string, count: int}>
     */
    protected function moduleCounts(Collection $active): array
    {
        return array_map(fn (TenantModule $module) => [
            'value' => $module->value,
            'label' => $module->label(),
            'count' => $active->filter(fn (Tenant $tenant) => $tenant->hasModule($module))->count(),
        ], TenantModule::cases());
    }

    /**
     * How many active tenants are in each industry, most first, leaving out industries with none
     *
     * @param  Collection<int, Tenant>  $active
     * @return list<array{value: string, label: string, count: int}>
     */
    protected function industryCounts(Collection $active): array
    {
        $counts = $active->countBy(fn (Tenant $tenant) => $tenant->industryProfile()->key);

        return array_values(collect($this->industries->all())
            ->map(fn (IndustryProfile $profile) => [
                ...$profile->toSelectItem(),
                'count' => (int) $counts->get($profile->key, 0),
            ])
            ->filter(fn (array $industry) => $industry['count'] > 0)
            ->sortByDesc('count')
            ->all());
    }

    protected function failedJobCount(): int
    {
        return $this->failedJobs instanceof CountableFailedJobProvider
            ? $this->failedJobs->count()
            : count($this->failedJobs->all());
    }
}

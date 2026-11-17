<?php

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    // Stops the tenant databases from being created
    Event::fake([TenantCreated::class]);
});

/**
 * A tenant on a subdomain of the central domain, e.g. acme.localhost
 *
 * @param  array<string, mixed>  $attributes
 */
function dashboardTenant(string $name, array $attributes = [], ?string $domain = null): Tenant
{
    $tenant = Tenant::factory()->create(['name' => $name, ...$attributes]);
    $tenant->domains()->create(['domain' => $domain ?? $name.'.'.config('tenancy.identification.central_domains')[0]]);

    return $tenant;
}

test('the dashboard counts active, disabled and new tenants', function () {
    // A tenant keeps a created_at it's given in its data column, so travel back to create old ones
    $this->travelTo(now()->subDays(31), function () {
        dashboardTenant('old');
        dashboardTenant('off', ['disabled_at' => now()]);
    });
    dashboardTenant('fresh');

    $this->get(route('central.admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('stats', ['active' => 2, 'disabled' => 1, 'new' => 1, 'new_days' => 30])
        );
});

test('active tenants with something to fix are listed with what is wrong, then disabled ones', function () {
    $connected = [
        'modules' => [TenantModule::Bookings],
        'stripe_charges_enabled' => true,
        'platform_fee_percent' => 2,
    ];

    dashboardTenant('ready', [...$connected, 'stripe_account_id' => 'acct_ready'], 'ready.test');
    dashboardTenant('subdomain', [...$connected, 'stripe_account_id' => 'acct_subdomain']);
    dashboardTenant('unpaid', ['modules' => [TenantModule::Bookings], 'stripe_account_id' => 'acct_unpaid'], 'unpaid.test');
    dashboardTenant('no-bookings', [], 'no-bookings.test');
    dashboardTenant('off', ['disabled_at' => now()->subDay()]);

    $this->get(route('central.admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 3)
            ->where('attention.0.name', 'subdomain')
            ->where('attention.0.issues', ['no_own_domain'])
            ->where('attention.1.name', 'unpaid')
            ->where('attention.1.issues', ['stripe', 'no_fee'])
            ->where('attention.2.name', 'off')
            ->where('attention.2.issues', ['disabled'])
            ->whereNot('attention.2.disabled_at', null)
        );
});

test('module and industry counts only include active tenants', function () {
    dashboardTenant('salon', ['modules' => [TenantModule::Bookings, TenantModule::Cms], 'industry' => 'beauty']);
    dashboardTenant('blog', ['modules' => [TenantModule::Cms]]);
    dashboardTenant('off', ['modules' => [TenantModule::Bookings], 'industry' => 'beauty', 'disabled_at' => now()]);

    $this->get(route('central.admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('modules', count(TenantModule::cases()))
            ->where('modules', fn ($modules) => collect($modules)->pluck('count', 'value')->only(['bookings', 'cms', 'crm'])->all() == ['bookings' => 1, 'cms' => 2, 'crm' => 0])
            ->where('industries', fn ($industries) => collect($industries)->pluck('count', 'value')->all() == ['beauty' => 1, config('industries.fallback') => 1])
        );
});

test('the newest tenants are listed first', function () {
    $this->travelTo(now()->subDays(2), fn () => dashboardTenant('older'));
    $this->travelTo(now()->subDay(), fn () => dashboardTenant('newer'));

    $this->get(route('central.admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recent.0.name', 'newer')
            ->where('recent.1.name', 'older')
        );
});

test('the dashboard shows how many jobs have failed', function () {
    app(FailedJobProviderInterface::class)->log('database', 'default', json_encode(['uuid' => (string) Str::uuid()]), new RuntimeException('Failed'));

    $this->get(route('central.admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('queue.failed', 1));
});

test('the dashboard needs a logged in central user', function () {
    auth()->logout();

    $this->get(route('central.admin.dashboard'))->assertRedirect(route('central.login'));
});

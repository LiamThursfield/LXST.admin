<?php

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function () {
    $this->withoutVite();

    // Stops the tenant database from being created
    Event::fake([TenantCreated::class]);
});

/**
 * @param  list<string>  $domains
 * @param  list<TenantModule>  $modules
 */
function centralTenant(string $name, array $domains = [], array $modules = []): Tenant
{
    $tenant = Tenant::create(['name' => $name, 'modules' => $modules]);

    foreach ($domains as $domain) {
        $tenant->domains()->create(['domain' => $domain]);
    }

    return $tenant;
}

describe('tenants list', function () {
    test('lists tenants with their domains and modules, sorted by name', function () {
        $this->actingAs(User::factory()->create());
        centralTenant('Zebra Co', ['zebra.test'], [TenantModule::Cms, TenantModule::Crm]);
        centralTenant('Acme', ['acme.test']);

        $this->get(route('central.admin.tenants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tenant/Index')
                ->has('tenants.data', 2)
                ->where('tenants.data.0.name', 'Acme')
                ->where('tenants.data.1.domains.0.domain', 'zebra.test')
                ->where('tenants.data.1.modules', [
                    ['value' => 'cms', 'label' => TenantModule::Cms->label()],
                    ['value' => 'crm', 'label' => TenantModule::Crm->label()],
                ])
                ->where('tenants.data.1.actions.0.url', route('central.admin.tenants.show', Tenant::query()->firstWhere('name', 'Zebra Co')))
            );
    });

    test('tenants can be searched by name, ID or domain, and filtered by module', function () {
        $this->actingAs(User::factory()->create());
        $acme = centralTenant('Acme', ['acme.test'], [TenantModule::Cms]);
        centralTenant('Globex', ['globex.example'], [TenantModule::Tracker]);

        $names = fn (array $query) => collect($this->get(route('central.admin.tenants.index', $query))->viewData('page')['props']['tenants']['data'])
            ->pluck('name')
            ->all();

        expect($names(['filter' => ['search' => 'acm']]))->toBe(['Acme'])
            ->and($names(['filter' => ['search' => 'globex.ex']]))->toBe(['Globex'])
            ->and($names(['filter' => ['search' => substr($acme->id, 0, 8)]]))->toBe(['Acme'])
            ->and($names(['filter' => ['module' => 'tracker']]))->toBe(['Globex']);
    });

    test('tenants can be sorted', function () {
        $this->actingAs(User::factory()->create());
        centralTenant('Acme');
        centralTenant('Globex');

        $this->get(route('central.admin.tenants.index', ['sort' => '-name']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sort', '-name')
                ->where('tenants.data.0.name', 'Globex')
            );
    });

    test('filters and sorts must be valid', function () {
        $this->actingAs(User::factory()->create());

        $this->get(route('central.admin.tenants.index', ['filter' => ['module' => 'blog'], 'sort' => 'domains']))
            ->assertSessionHasErrors(['filter.module', 'sort']);
    });
});

test('a tenant\'s page shows its details and links to its admin', function () {
    $this->actingAs(User::factory()->create());
    $tenant = centralTenant('Acme', ['acme', 'acme.test'], [TenantModule::Tracker]);

    $this->get(route('central.admin.tenants.show', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tenant/Show')
            ->where('tenant.data.id', $tenant->id)
            ->where('tenant.data.name', 'Acme')
            ->has('tenant.data.domains', 2)
            // A bare subdomain isn't reachable on its own, so the admin link uses the full domain
            ->where('tenant.data.admin_url', 'http://acme.test/admin')
            ->where('tenant.data.domains.0.admin_url', null)
            ->where('tenant.data.modules.0.value', 'tracker')
        );
});

test('the tenant pages need a logged in central user', function () {
    $tenant = centralTenant('Acme');

    $this->get(route('central.admin.tenants.index'))->assertRedirect(route('central.login'));
    $this->get(route('central.admin.tenants.show', $tenant))->assertRedirect(route('central.login'));
});

test('the central menu links to the tenants list', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('central.admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('menus.main.1.0.label', __('navigation.tenants'))
            ->where('menus.main.1.0.to', route('central.admin.tenants.index', absolute: false))
        );
});

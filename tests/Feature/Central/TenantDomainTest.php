<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create());

    // Stops the tenant database from being created
    Event::fake([TenantCreated::class]);
    $this->tenant = Tenant::query()->create(['name' => 'acme']);
    $this->domain = $this->tenant->domains()->create(['domain' => 'acme.test']);
});

test('a domain can be added', function () {
    $this->post(route('central.admin.tenants.domains.store', $this->tenant), ['domain' => ' WWW.Acme.TEST '])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('tenants.domains.flash.added', ['domain' => 'www.acme.test']));

    expect($this->tenant->domains()->pluck('domain')->all())->toEqualCanonicalizing(['acme.test', 'www.acme.test']);
});

test('a bare name is a subdomain of the central domain', function () {
    $this->post(route('central.admin.tenants.domains.store', $this->tenant), ['domain' => 'acme-staging'])
        ->assertSessionHasNoErrors();

    $central = config('tenancy.identification.central_domains')[0];

    expect($this->tenant->domains()->where('domain', "acme-staging.{$central}")->exists())->toBeTrue();
});

test('a domain must be valid, free and not the central domain', function (string $domain, string $message) {
    $other = Tenant::query()->create(['name' => 'globex']);
    $other->domains()->create(['domain' => 'globex.test']);

    $this->post(route('central.admin.tenants.domains.store', $this->tenant), ['domain' => $domain])
        ->assertSessionHasErrors('domain');

    expect(session('errors')->first('domain'))->toBe(__($message));
})->with([
    'with a scheme' => ['https://acme.test/', 'tenants.validation.domain_format'],
    'another tenant\'s' => ['globex.test', 'tenants.validation.domain_taken'],
    'its own' => ['acme.test', 'tenants.validation.domain_taken'],
    'the central domain' => ['localhost', 'tenants.validation.domain_central'],
]);

test('a domain can be changed, keeping its own name allowed', function () {
    $this->put(route('central.admin.tenants.domains.update', [$this->tenant, $this->domain]), ['domain' => 'acme.test'])
        ->assertSessionHasNoErrors();

    $this->put(route('central.admin.tenants.domains.update', [$this->tenant, $this->domain]), ['domain' => 'acme.example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('tenants.domains.flash.updated', ['domain' => 'acme.example.com']));

    expect($this->domain->refresh()->domain)->toBe('acme.example.com');
});

test('a domain can be removed, but not the tenant\'s last one', function () {
    $this->delete(route('central.admin.tenants.domains.destroy', [$this->tenant, $this->domain]))
        ->assertSessionHasErrors(['domain' => __('tenants.domains.validation.last_domain')]);

    $second = $this->tenant->domains()->create(['domain' => 'www.acme.test']);

    $this->delete(route('central.admin.tenants.domains.destroy', [$this->tenant, $second]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('tenants.domains.flash.removed', ['domain' => 'www.acme.test']));

    expect($this->tenant->domains()->pluck('domain')->all())->toBe(['acme.test']);
});

test('another tenant\'s domain can\'t be changed through this tenant', function () {
    $other = Tenant::query()->create(['name' => 'globex']);
    $otherDomain = $other->domains()->create(['domain' => 'globex.test']);

    $this->put(route('central.admin.tenants.domains.update', [$this->tenant, $otherDomain]), ['domain' => 'stolen.test'])
        ->assertNotFound();
    $this->delete(route('central.admin.tenants.domains.destroy', [$this->tenant, $otherDomain]))
        ->assertNotFound();

    expect(Domain::query()->find($otherDomain->id)?->domain)->toBe('globex.test');
});

test('managing domains needs a logged in central user', function () {
    auth()->logout();

    $this->post(route('central.admin.tenants.domains.store', $this->tenant), ['domain' => 'www.acme.test'])
        ->assertRedirect(route('central.login'));
});

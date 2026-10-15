<?php

use App\Enums\TenantModule;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function () {
    // Stops the tenant database from being created
    Event::fake([TenantCreated::class]);
});

test('it creates a tenant with the modules given in the option', function () {
    $this->artisan('central:make:tenant', ['--modules' => 'cms, tracker'])
        ->expectsQuestion('What is the tenant name?', 'Acme')
        ->expectsQuestion('Add a domain? (leave blank to skip)', 'acme.test')
        ->expectsQuestion('Add a domain? (leave blank to skip)', '')
        ->assertSuccessful();

    $tenant = Tenant::query()->where('name', 'acme')->firstOrFail();

    expect($tenant->modules->all())->toBe([TenantModule::Cms, TenantModule::Tracker]);
});

test('it creates a tenant with the modules chosen in the prompt when the option is omitted', function () {
    $this->artisan('central:make:tenant')
        ->expectsQuestion('What is the tenant name?', 'Acme')
        ->expectsQuestion('Which modules should the tenant have?', ['crm'])
        ->expectsQuestion('Add a domain? (leave blank to skip)', 'acme.test')
        ->expectsQuestion('Add a domain? (leave blank to skip)', '')
        ->assertSuccessful();

    expect(Tenant::query()->where('name', 'acme')->firstOrFail()->modules->all())
        ->toBe([TenantModule::Crm]);
});

test('it does not create a tenant when the option contains an unknown module', function () {
    $this->artisan('central:make:tenant', ['--modules' => 'cms,blog'])
        ->expectsQuestion('What is the tenant name?', 'Acme')
        ->expectsOutputToContain('Unknown module(s): blog')
        ->assertSuccessful();

    expect(Tenant::query()->where('name', 'acme')->exists())->toBeFalse();
});

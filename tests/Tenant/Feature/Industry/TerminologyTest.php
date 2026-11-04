<?php

use App\Enums\BookingType;
use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Industry\IndustryProfiles;
use App\Services\Industry\Terminology;
use Illuminate\Support\Facades\Lang;
use Inertia\Testing\AssertableInertia as Assert;

test('every industry has a name, a description, known defaults and complete words', function () {
    foreach (app(IndustryProfiles::class)->all() as $profile) {
        $terms = app(Terminology::class)->defaultsFor($profile->key);

        expect(Lang::has("industries.names.{$profile->key}"))->toBeTrue()
            ->and(Lang::has("industries.descriptions.{$profile->key}"))->toBeTrue()
            ->and($profile->suggestedModules)->each->toBeInstanceOf(TenantModule::class)
            ->and($profile->bookingTypes)->not->toBeEmpty()->each->toBeInstanceOf(BookingType::class)
            ->and(array_keys($terms))->toBe(app(Terminology::class)->termKeys());

        foreach ($profile->customerFields as $field) {
            expect(config("industries.customer_fields.{$field}"))->not->toBeNull();
        }

        foreach ($terms as $term) {
            expect($term)->toHaveKeys(['singular', 'plural']);
        }
    }
});

test('every term and customer field has a label', function () {
    foreach (app(Terminology::class)->termKeys() as $term) {
        expect(Lang::has("industries.term_names.{$term}"))->toBeTrue();
    }

    foreach (array_keys(config('industries.customer_fields')) as $field) {
        expect(Lang::has("industries.customer_fields.{$field}"))->toBeTrue();
    }
});

test('a term comes from the tenant, then its industry, then the general word', function () {
    tenant()->update([
        'industry' => 'pets',
        'industry_overrides' => ['terms' => ['booking' => ['singular' => 'groom', 'plural' => 'grooms']]],
    ]);

    $terms = app(Terminology::class)->forTenant();

    expect($terms['booking'])->toEqual(['singular' => 'groom', 'plural' => 'grooms'])
        ->and($terms['customer'])->toEqual(['singular' => 'owner', 'plural' => 'owners'])
        // Pets has no word of its own for this
        ->and($terms['location'])->toEqual(['singular' => 'location', 'plural' => 'locations']);
});

test('changing industry keeps the tenant\'s own words', function () {
    tenant()->update([
        'industry' => 'hospitality',
        'industry_overrides' => ['terms' => ['staff_member' => ['singular' => 'waiter', 'plural' => 'waiters']]],
    ]);

    tenant()->update(['industry' => 'health']);

    $terms = app(Terminology::class)->forTenant();

    expect($terms['staff_member']['singular'])->toBe('waiter')
        ->and($terms['customer']['singular'])->toBe('patient');
});

test('a tenant whose industry is no longer listed uses the general one', function () {
    tenant()->update(['industry' => 'long-gone']);

    expect(tenant()->industryProfile()->key)->toBe('other')
        ->and(app(Terminology::class)->forTenant()['customer']['singular'])->toBe('customer');
});

test('without a tenant, the general words are used', function () {
    $tenant = tenant();
    tenant()->update(['industry' => 'trades']);

    tenancy()->end();

    expect(app(Terminology::class)->forTenant()['booking']['singular'])->toBe('booking');

    tenancy()->initialize($tenant);

    expect(app(Terminology::class)->forTenant()['booking']['singular'])->toBe('job');
});

test('a line can use the tenant\'s words as placeholders', function () {
    tenant()->update(['industry' => 'fitness']);
    Lang::addLines(['test.line' => ':Customers book :services with :staff_members. :name!'], 'en');

    expect(app(Terminology::class)->translate('test.line', ['name' => 'Done']))
        ->toBe('Members book classes with coaches. Done!');
});

test('admin pages are given the tenant\'s words as placeholders', function () {
    tenant()->update([
        'industry' => 'education',
        'industry_overrides' => ['terms' => ['staff_member' => ['singular' => 'mentor', 'plural' => 'mentors']]],
    ]);
    $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]));

    $this->get(route('admin.site-settings.general.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('terminology.customer', 'student')
            ->where('terminology.customers', 'students')
            ->where('terminology.staff_member', 'mentor')
            ->where('terminology.staff_members', 'mentors')
        );
});

test('the factory can make a tenant in an industry, with its own words', function () {
    $tenant = Tenant::factory()
        ->industry('retail')
        ->withTerms(['service' => ['singular' => 'bouquet', 'plural' => 'bouquets']])
        ->make();

    expect(app(Terminology::class)->forTenant($tenant)['service']['plural'])->toBe('bouquets')
        ->and(app(Terminology::class)->forTenant($tenant)['location']['singular'])->toBe('shop');
});

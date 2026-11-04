<?php

use App\Enums\BookingType;
use App\Enums\TenantModule;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Industry\Terminology;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * What the settings page sends when nothing has been changed from the industry's defaults
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function industryInput(string $industry = 'beauty', array $overrides = []): array
{
    return [
        'industry' => $industry,
        'terms' => [],
        'booking_types' => array_map(fn (BookingType $type) => $type->value, config("industries.profiles.{$industry}.booking_types")),
        'customer_fields' => config("industries.profiles.{$industry}.customer_fields"),
        ...$overrides,
    ];
}

describe('viewing', function () {
    test('the page shows the tenant\'s industry, what it has changed and every option', function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
        tenant()->update([
            'industry' => 'health',
            'industry_overrides' => ['terms' => ['customer' => ['singular' => 'client', 'plural' => 'clients']]],
        ]);

        $this->get(route('admin.site-settings.industry.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/site-settings/Industry')
                ->where('settings.industry', 'health')
                ->where('settings.terms.customer.singular', 'client')
                ->missing('settings.terms.booking')
                ->where('settings.booking_types', ['appointment', 'class'])
                ->where('settings.customer_fields', ['medical_history', 'injuries', 'allergies', 'emergency_contact'])
                ->has('industries', count(config('industries.profiles')))
                ->has('termNames', count(app(Terminology::class)->termKeys()))
                ->has('bookingTypes', count(BookingType::cases()))
                ->has('customerFields', count(config('industries.customer_fields')))
                ->where('canManage', true)
            );
    });

    test('a user who can only view sees it read only, and can\'t save', function () {
        $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]));

        $this->get(route('admin.site-settings.industry.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->put(route('admin.site-settings.industry.update'), industryInput())->assertForbidden();

        expect(tenant()->refresh()->industry)->toBe('other');
    });

    test('a user without the settings permissions can\'t open it', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.site-settings.industry.edit'))->assertForbidden();
    });

    test('the page is in the site settings menu for users who can view settings', function () {
        $menuFor = fn ($user) => json_encode(
            app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)->toArray(),
            JSON_UNESCAPED_SLASHES,
        );
        $url = route('admin.site-settings.industry.edit', [], false);

        expect($menuFor($this->createUserWithPermissions([SettingsPermission::ViewSettings])))->toContain($url)
            ->and($menuFor($this->createUserWithPermissions([])))->not->toContain($url);
    });
});

describe('saving', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
    });

    test('choosing an industry stores nothing else while everything is its default', function () {
        $this->put(route('admin.site-settings.industry.update'), industryInput('beauty', [
            'terms' => ['customer' => ['singular' => null, 'plural' => null]],
        ]))
            ->assertRedirect(route('admin.site-settings.industry.edit'))
            ->assertSessionHas('success', __('site_settings.industry.updated'));

        $tenant = tenant()->refresh();

        expect($tenant->industry)->toBe('beauty')
            ->and($tenant->industry_overrides)->toBeNull()
            ->and($tenant->bookingTypes())->toBe([BookingType::Appointment, BookingType::Enquiry])
            ->and($tenant->customerFields())->toBe(['colour_formula', 'patch_test_date', 'allergies']);
    });

    test('any term can be overridden, and only what differs from the industry is stored', function () {
        $this->put(route('admin.site-settings.industry.update'), industryInput('beauty', [
            'terms' => [
                'staff_member' => ['singular' => 'barber', 'plural' => 'barbers'],
                // The same as beauty's own word
                'customer' => ['singular' => 'client', 'plural' => 'clients'],
            ],
        ]))->assertSessionHasNoErrors();

        $tenant = tenant()->refresh();

        expect($tenant->termOverrides())->toEqual(['staff_member' => ['singular' => 'barber', 'plural' => 'barbers']])
            ->and(app(Terminology::class)->forTenant($tenant)['staff_member']['plural'])->toBe('barbers')
            ->and(app(Terminology::class)->forTenant($tenant)['customer']['singular'])->toBe('client');
    });

    test('booking types and customer fields can be changed, then set back to the industry\'s', function () {
        $this->put(route('admin.site-settings.industry.update'), industryInput('beauty', [
            'booking_types' => ['class'],
            'customer_fields' => [],
        ]))->assertSessionHasNoErrors();

        $tenant = tenant()->refresh();

        expect($tenant->bookingTypes())->toBe([BookingType::GroupClass])
            ->and($tenant->customerFields())->toBe([]);

        $this->put(route('admin.site-settings.industry.update'), industryInput('beauty'))->assertSessionHasNoErrors();

        expect(tenant()->refresh()->industry_overrides)->toBeNull();
    });

    test('a term needs both its singular and plural', function () {
        $this->put(route('admin.site-settings.industry.update'), industryInput('beauty', [
            'terms' => ['customer' => ['singular' => 'guest', 'plural' => null]],
        ]))->assertSessionHasErrors(['terms.customer.plural' => __('site_settings.industry.validation.plural_required')]);
    });

    test('unknown options are rejected', function (array $input, string $error) {
        $this->put(route('admin.site-settings.industry.update'), industryInput('beauty', $input))
            ->assertSessionHasErrors($error);

        expect(tenant()->refresh()->industry)->toBe('other');
    })->with([
        'an industry' => [['industry' => 'piracy'], 'industry'],
        'a term' => [['terms' => ['pirate' => ['singular' => 'pirate', 'plural' => 'pirates']]], 'terms'],
        'a booking type' => [['booking_types' => ['plunder']], 'booking_types.0'],
        'a customer field' => [['customer_fields' => ['parrot']], 'customer_fields.0'],
    ]);

    test('saving refreshes the headless API\'s cached site', function () {
        $this->getJson(route('api.v1.site'))->assertJsonPath('data.industry', 'other');

        $this->put(route('admin.site-settings.industry.update'), industryInput('health', [
            'terms' => ['location' => ['singular' => 'practice', 'plural' => 'practices']],
        ]))->assertSessionHasNoErrors();

        $this->getJson(route('api.v1.site'))
            ->assertJsonPath('data.industry', 'health')
            ->assertJsonPath('data.terminology.customer', ['singular' => 'patient', 'plural' => 'patients'])
            ->assertJsonPath('data.terminology.location', ['singular' => 'practice', 'plural' => 'practices']);
    });
});

describe('module gating', function () {
    test('the page works whichever modules the tenant has, as every tenant has an industry', function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
        tenant()->update(['modules' => []]);

        $this->get(route('admin.site-settings.industry.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('activeModules', []));
    });

    test('choosing an industry doesn\'t switch modules on or off, even when asked to', function () {
        $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Tracker]]);

        $this->put(route('admin.site-settings.industry.update'), industryInput('events', [
            'modules' => ['cms', 'crm', 'dj'],
        ]))->assertSessionHasNoErrors();

        expect(tenant()->refresh()->modules->all())->toBe([TenantModule::Tracker]);
    });
});

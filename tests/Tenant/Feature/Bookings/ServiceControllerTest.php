<?php

use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\ServiceOptionType;
use App\Enums\Bookings\ServicePriceType;
use App\Enums\BookingType;
use App\Enums\TenantModule;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceCategory;
use App\Models\Bookings\ServiceOption;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\NavigationService;
use Inertia\Testing\AssertableInertia as Assert;

function validServiceData(array $overrides = []): array
{
    return [
        'name' => 'Haircut',
        'slug' => '',
        'description' => 'A wash and cut.',
        'category_id' => null,
        'image_file_id' => null,
        'booking_type' => 'appointment',
        'is_bookable_online' => true,
        'price_type' => 'fixed',
        'price' => '19.99',
        'duration_minutes' => 45,
        'buffer_before_minutes' => 5,
        'buffer_after_minutes' => 10,
        'options' => [],
        'location_ids' => [],
        ...$overrides,
    ];
}

describe('access', function () {
    test('users without the view permission cannot see services', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.bookings.services.index'))->assertForbidden();
    });

    test('users with only the view permission can see services but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewServices]));
        $service = Service::factory()->create();

        $this->get(route('admin.bookings.services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/bookings/service/Index')
                ->has('services.data', 1)
                ->where('canCreate', false)
            );

        $this->get(route('admin.bookings.services.show', $service))->assertOk();
        $this->get(route('admin.bookings.services.create'))->assertForbidden();
        $this->post(route('admin.bookings.services.store'), validServiceData())->assertForbidden();
        $this->put(route('admin.bookings.services.update', $service), validServiceData())->assertForbidden();
        $this->delete(route('admin.bookings.services.destroy', $service))->assertForbidden();
    });

    test('the pages return not found without the Bookings module, and the menu hides them', function () {
        $user = $this->createUserWithPermissions(BookingsPermission::cases());
        $this->actingAs($user);

        $menuHasServices = fn () => str_contains(
            json_encode(app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)->toArray(), JSON_UNESCAPED_SLASHES),
            route('admin.bookings.services.index', [], false)
        );

        expect($menuHasServices())->toBeTrue();

        tenant()->update(['modules' => [TenantModule::Crm]]);

        expect($menuHasServices())->toBeFalse();
        $this->get(route('admin.bookings.services.index'))->assertNotFound();
        $this->get(route('admin.bookings.service-categories.index'))->assertNotFound();
    });
});

describe('saving', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
    });

    test('it creates a service, storing the price in minor units and making a slug', function () {
        $this->post(route('admin.bookings.services.store'), validServiceData())
            ->assertRedirect(route('admin.bookings.services.index'));

        $service = Service::query()->sole();

        expect($service->slug)->toBe('haircut')
            ->and($service->price)->toBe(1999)
            ->and($service->duration_minutes)->toBe(45)
            ->and($service->buffer_after_minutes)->toBe(10);
    });

    test('a slug is not reused from a deleted service', function () {
        Service::factory()->create(['slug' => 'haircut'])->delete();

        $this->post(route('admin.bookings.services.store'), validServiceData())->assertSessionHasNoErrors();

        expect(Service::query()->sole()->slug)->toBe('haircut-2');
    });

    test('details not used by the booking type are cleared, and not checked', function () {
        $service = Service::factory()->groupClass()->create();

        $this->put(route('admin.bookings.services.update', $service), validServiceData([
            'name' => 'Wedding DJ',
            'booking_type' => 'enquiry',
            'price_type' => 'on_quote',
            'price' => 'not a number',
            'duration_minutes' => null,
            'capacity' => -1,
            'buffer_after_minutes' => 30,
        ]))->assertSessionHasNoErrors();

        $service->refresh();

        expect($service->booking_type)->toBe(BookingType::Enquiry)
            ->and($service->price)->toBeNull()
            ->and($service->duration_minutes)->toBeNull()
            ->and($service->capacity)->toBeNull()
            ->and($service->buffer_after_minutes)->toBe(0);
    });

    test('each booking type requires its own details', function (string $type, array $missing) {
        $this->post(route('admin.bookings.services.store'), [
            'name' => 'A service',
            'booking_type' => $type,
            'price_type' => 'on_quote',
        ])->assertSessionHasErrors($missing);

        expect(Service::query()->count())->toBe(0);
    })->with([
        'appointment' => ['appointment', ['duration_minutes']],
        'class' => ['class', ['duration_minutes', 'capacity']],
        'table' => ['table', ['duration_minutes', 'min_party_size', 'max_party_size']],
        'hire' => ['hire', ['hire_unit', 'min_hire_units']],
        'visit' => ['visit', ['duration_minutes']],
        'ticket' => ['ticket', ['duration_minutes', 'capacity']],
    ]);

    test('every booking type can be saved with its own details', function (string $type, array $details, string $column, mixed $expected) {
        $this->post(route('admin.bookings.services.store'), [
            'name' => 'A service',
            'booking_type' => $type,
            'price_type' => 'on_quote',
            ...$details,
        ])->assertSessionHasNoErrors();

        expect(Service::query()->sole()->{$column})->toBe($expected);
    })->with([
        'a haircut with a stylist' => ['appointment', ['duration_minutes' => 30], 'duration_minutes', 30],
        'a spin class for 20' => ['class', ['duration_minutes' => 45, 'capacity' => 20], 'capacity', 20],
        'a table for 6' => ['table', ['duration_minutes' => 90, 'min_party_size' => 2, 'max_party_size' => 6], 'max_party_size', 6],
        'a meeting room by the hour' => ['hire', ['hire_unit' => 'hour', 'min_hire_units' => 2, 'max_hire_units' => null], 'hire_unit', HireUnit::Hour],
        'a boiler service at home' => ['visit', ['duration_minutes' => 60, 'travel_minutes' => 30], 'travel_minutes', 30],
        'an event ticket' => ['ticket', ['duration_minutes' => 180, 'capacity' => 100], 'capacity', 100],
        'a DJ for a wedding' => ['enquiry', [], 'booking_type', BookingType::Enquiry],
    ]);

    test('a price is needed unless it is on quote', function () {
        $this->post(route('admin.bookings.services.store'), validServiceData(['price' => '']))
            ->assertSessionHasErrors('price');

        $this->post(route('admin.bookings.services.store'), validServiceData(['price_type' => 'from', 'price' => '12.345']))
            ->assertSessionHasErrors('price');

        $this->post(route('admin.bookings.services.store'), validServiceData(['price_type' => 'on_quote', 'price' => '']))
            ->assertSessionHasNoErrors();

        expect(Service::query()->sole()->price_type)->toBe(ServicePriceType::OnQuote);
    });

    test('the largest party and longest hire cannot be below the smallest', function () {
        $this->post(route('admin.bookings.services.store'), validServiceData([
            'booking_type' => 'table',
            'min_party_size' => 6,
            'max_party_size' => 2,
        ]))->assertSessionHasErrors('max_party_size');

        $this->post(route('admin.bookings.services.store'), validServiceData([
            'booking_type' => 'hire',
            'hire_unit' => 'day',
            'min_hire_units' => 3,
            'max_hire_units' => 1,
        ]))->assertSessionHasErrors('max_hire_units');
    });

    test('options are saved in order, keeping the ids of those that stay', function () {
        $service = Service::factory()->create();
        $kept = ServiceOption::factory()->for($service)->create(['name' => 'Long hair']);
        $removed = ServiceOption::factory()->for($service)->create(['name' => 'Gone']);

        $this->put(route('admin.bookings.services.update', $service), validServiceData([
            'options' => [
                ['id' => null, 'type' => 'add_on', 'name' => 'Beard trim', 'price_adjustment' => '5.50', 'duration_adjustment' => 10],
                ['id' => $kept->id, 'type' => 'variation', 'name' => 'Short hair', 'price_adjustment' => '-3', 'duration_adjustment' => -5],
            ],
        ]))->assertSessionHasNoErrors();

        $options = $service->refresh()->options;

        expect($options->pluck('name')->all())->toBe(['Beard trim', 'Short hair'])
            ->and($options[0]->type)->toBe(ServiceOptionType::AddOn)
            ->and($options[0]->price_adjustment)->toBe(550)
            ->and($options[1]->id)->toBe($kept->id)
            ->and($options[1]->price_adjustment)->toBe(-300)
            ->and($options[1]->duration_adjustment)->toBe(-5)
            ->and(ServiceOption::query()->find($removed->id))->toBeNull();
    });

    test('an option from another service cannot be changed', function () {
        $service = Service::factory()->create();
        $other = ServiceOption::factory()->create();

        $this->put(route('admin.bookings.services.update', $service), validServiceData([
            'options' => [['id' => $other->id, 'type' => 'variation', 'name' => 'Stolen']],
        ]))->assertSessionHasErrors('options.0.id');
    });

    test('only locations can be chosen, and a service with none is offered at all of them', function () {
        $here = OrganisationUnit::factory()->location()->create();
        $there = OrganisationUnit::factory()->location()->create();
        $company = OrganisationUnit::factory()->company()->create();

        $this->post(route('admin.bookings.services.store'), validServiceData(['location_ids' => [$company->id]]))
            ->assertSessionHasErrors('location_ids.0');

        $this->post(route('admin.bookings.services.store'), validServiceData(['location_ids' => [$here->id]]))
            ->assertSessionHasNoErrors();
        $everywhere = Service::factory()->create();

        expect(Service::query()->offeredAt($here)->count())->toBe(2)
            ->and(Service::query()->offeredAt($there)->pluck('id')->all())->toBe([$everywhere->id]);
    });

    test('without the CRM, a service keeps its locations and none are offered', function () {
        $location = OrganisationUnit::factory()->location()->create();
        $service = Service::factory()->create();
        $service->locations()->attach($location);

        tenant()->update(['modules' => [TenantModule::Bookings]]);

        $this->get(route('admin.bookings.services.edit', $service))
            ->assertInertia(fn (Assert $page) => $page->where('hasLocations', false)->has('locations', 0));

        $this->put(route('admin.bookings.services.update', $service), validServiceData())->assertSessionHasNoErrors();

        expect($service->locations()->count())->toBe(1);
    });

    test('the tenant\'s booking types are offered first', function () {
        tenant()->update(['industry' => 'fitness']);

        $this->get(route('admin.bookings.services.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('bookingTypes', count(BookingType::cases()))
                ->where('bookingTypes.0.value', BookingType::GroupClass->value)
                ->where('bookingTypes.0.details', BookingType::GroupClass->details())
            );
    });

    test('deleting keeps the service for past bookings', function () {
        $service = Service::factory()->create();

        $this->delete(route('admin.bookings.services.destroy', $service))->assertRedirect();

        expect($service->refresh()->trashed())->toBeTrue();
    });

    test('the flash message and menu use the tenant\'s words', function () {
        tenant()->update(['industry' => 'beauty']);

        $this->post(route('admin.bookings.services.store'), validServiceData())
            ->assertSessionHas('success', 'Treatment created.');

        $menu = json_encode(app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, auth()->user())->toArray());

        expect($menu)->toContain('"Treatments"')->not->toContain(':Services');
    });
});

describe('categories', function () {
    test('viewers can list them but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewServices]));
        $category = ServiceCategory::factory()->create();

        $this->get(route('admin.bookings.service-categories.index'))->assertOk();
        $this->post(route('admin.bookings.service-categories.store'), ['name' => 'Cuts'])->assertForbidden();
        $this->delete(route('admin.bookings.service-categories.destroy', $category))->assertForbidden();
    });

    test('they can be managed, with unique names', function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));

        $this->post(route('admin.bookings.service-categories.store'), ['name' => 'Cuts', 'order' => 5])
            ->assertRedirect(route('admin.bookings.service-categories.index'));
        $this->post(route('admin.bookings.service-categories.store'), ['name' => 'Cuts'])->assertSessionHasErrors('name');

        $category = ServiceCategory::query()->sole();

        $this->put(route('admin.bookings.service-categories.update', $category), ['name' => 'Cuts', 'order' => null])
            ->assertSessionHasNoErrors();

        expect($category->refresh()->order)->toBe(0);
    });

    test('deleting one leaves its services without a category', function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->for($category, 'category')->create();

        $this->delete(route('admin.bookings.service-categories.destroy', $category))->assertRedirect();

        expect($service->refresh()->category_id)->toBeNull();
    });
});

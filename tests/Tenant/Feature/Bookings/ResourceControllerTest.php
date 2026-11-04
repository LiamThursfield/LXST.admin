<?php

use App\Enums\Bookings\ResourceType;
use App\Enums\TenantModule;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Bookings\WorkingHour;
use App\Models\Crm\OrganisationUnit;
use App\Models\User;
use App\Services\Authorisation\Enums\BookingsPermission;
use Inertia\Testing\AssertableInertia as Assert;

function validResourceData(array $overrides = []): array
{
    return [
        'type' => 'person',
        'name' => 'Sam Taylor',
        'slug' => '',
        'is_active' => true,
        'is_public' => true,
        'job_title' => 'Stylist',
        'working_hours' => [],
        'services' => [],
        ...$overrides,
    ];
}

describe('access', function () {
    test('users without the view permission cannot see resources', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.bookings.resources.index'))->assertForbidden();
    });

    test('users with only the view permission can see resources but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewResources]));
        $resource = BookableResource::factory()->create();

        $this->get(route('admin.bookings.resources.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('resources.data', 1)->where('canCreate', false));

        $this->get(route('admin.bookings.resources.show', $resource))->assertOk();
        $this->post(route('admin.bookings.resources.store'), validResourceData())->assertForbidden();
        $this->put(route('admin.bookings.resources.update', $resource), validResourceData())->assertForbidden();
        $this->delete(route('admin.bookings.resources.destroy', $resource))->assertForbidden();
    });

    test('the pages return not found without the Bookings module', function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.bookings.resources.index'))->assertNotFound();
        $this->get(route('admin.bookings.rota.index'))->assertNotFound();
        $this->get(route('admin.bookings.time-off.index'))->assertNotFound();
        $this->get(route('admin.bookings.staff-hours.index'))->assertNotFound();
    });
});

describe('saving', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
    });

    test('it creates a person with working hours, services and their own price and length', function () {
        $user = User::factory()->create();
        $haircut = Service::factory()->create();
        $colour = Service::factory()->create();

        $this->post(route('admin.bookings.resources.store'), validResourceData([
            'user_id' => $user->id,
            'working_hours' => [
                ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00'],
                ['day_of_week' => 1, 'starts_at' => '14:00', 'ends_at' => '18:00'],
            ],
            'services' => [
                ['id' => $haircut->id, 'price' => '45.50', 'duration_minutes' => 30],
                ['id' => $colour->id, 'price' => null, 'duration_minutes' => null],
            ],
        ]))->assertRedirect(route('admin.bookings.resources.index'));

        $resource = BookableResource::query()->sole();

        expect($resource->slug)->toBe('sam-taylor')
            ->and($resource->user_id)->toBe($user->id)
            ->and($resource->workingHours()->count())->toBe(2)
            ->and($resource->services()->findOrFail($haircut->id)->pivot->price)->toBe(4550)
            ->and($resource->services()->findOrFail($haircut->id)->pivot->duration_minutes)->toBe(30)
            ->and($resource->services()->findOrFail($colour->id)->pivot->price)->toBeNull();
    });

    test('details that do not apply to the type are cleared', function () {
        $user = User::factory()->create();
        $resource = BookableResource::factory()->forUser($user)->create();

        $this->put(route('admin.bookings.resources.update', $resource), validResourceData([
            'type' => 'space',
            'name' => 'Room 1',
            'user_id' => $user->id,
            'capacity' => 8,
        ]))->assertSessionHasNoErrors();

        $resource->refresh();

        expect($resource->type)->toBe(ResourceType::Space)
            ->and($resource->user_id)->toBeNull()
            ->and($resource->job_title)->toBeNull()
            ->and($resource->capacity)->toBe(8);
    });

    test('a user can only be linked to one person, until that person is deleted', function () {
        $user = User::factory()->create();
        $first = BookableResource::factory()->forUser($user)->create();

        $this->post(route('admin.bookings.resources.store'), validResourceData(['user_id' => $user->id]))
            ->assertSessionHasErrors('user_id');

        $this->delete(route('admin.bookings.resources.destroy', $first))->assertRedirect();

        expect($first->refresh()->trashed())->toBeTrue()
            ->and($first->user_id)->toBeNull();

        $this->post(route('admin.bookings.resources.store'), validResourceData(['user_id' => $user->id]))
            ->assertSessionHasNoErrors();
    });

    test('working hours cannot overlap, including one running past midnight', function (array $hours) {
        $this->post(route('admin.bookings.resources.store'), validResourceData(['working_hours' => $hours]))
            ->assertSessionHasErrors('working_hours');
    })->with([
        'overlapping' => [[
            ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '13:00'],
            ['day_of_week' => 2, 'starts_at' => '12:00', 'ends_at' => '18:00'],
        ]],
        'past midnight' => [[
            ['day_of_week' => 2, 'starts_at' => '18:00', 'ends_at' => '02:00'],
            ['day_of_week' => 2, 'starts_at' => '20:00', 'ends_at' => '22:00'],
        ]],
    ]);

    test('saving replaces the working hours', function () {
        $resource = BookableResource::factory()->create();
        WorkingHour::factory()->for($resource, 'resource')->create(['day_of_week' => 1]);

        $this->put(route('admin.bookings.resources.update', $resource), validResourceData([
            'working_hours' => [['day_of_week' => 3, 'starts_at' => '10:00', 'ends_at' => '12:00']],
        ]))->assertSessionHasNoErrors();

        expect($resource->workingHours()->pluck('day_of_week')->all())->toBe([3]);
    });

    test('only a location can be chosen, and a deleted service cannot', function () {
        $company = OrganisationUnit::factory()->company()->create();
        $deleted = Service::factory()->create();
        $deleted->delete();

        $this->post(route('admin.bookings.resources.store'), validResourceData([
            'location_id' => $company->id,
            'services' => [['id' => $deleted->id]],
        ]))->assertSessionHasErrors(['location_id', 'services.0.id']);
    });

    test('without the CRM, a resource keeps its location and none are offered', function () {
        $location = OrganisationUnit::factory()->location()->create();
        $resource = BookableResource::factory()->create(['location_id' => $location->id]);

        tenant()->update(['modules' => [TenantModule::Bookings]]);

        $this->get(route('admin.bookings.resources.edit', $resource))
            ->assertInertia(fn (Assert $page) => $page->where('hasLocations', false)->has('locations', 0));

        $this->put(route('admin.bookings.resources.update', $resource), validResourceData(['location_id' => null]))
            ->assertSessionHasNoErrors();

        expect($resource->refresh()->location_id)->toBe($location->id);
    });

    test('a service shows who can do it, only to those who can see resources', function () {
        $service = Service::factory()->create();
        BookableResource::factory()->create(['name' => 'Sam'])->services()->attach($service);

        $this->get(route('admin.bookings.services.show', $service))
            ->assertInertia(fn (Assert $page) => $page->where('service.data.resource_names', ['Sam']));

        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewServices]));

        $this->get(route('admin.bookings.services.show', $service))
            ->assertInertia(fn (Assert $page) => $page->missing('service.data.resource_names'));
    });
});

<?php

use App\Enums\Crm\LocationType;
use App\Models\Crm\OpeningHour;
use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\CrmPermission;
use Inertia\Testing\AssertableInertia as Assert;

function locationFormData(OrganisationUnit $company, array $overrides = []): array
{
    return [
        'type' => 'location',
        'parent_id' => $company->id,
        'name' => 'The Groomer',
        'email' => 'hello@groomer.test',
        'address' => null,
        'location_type' => 'mobile',
        'timezone' => 'Europe/London',
        'service_areas' => ['M1', '', 'SK1'],
        'opening_hours' => [
            ['day_of_week' => 1, 'opens_at' => '12:00', 'closes_at' => '14:00', 'label' => 'Lunch'],
            ['day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '22:00', 'label' => 'Dinner'],
        ],
        'opening_hour_exceptions' => [
            ['name' => 'Christmas', 'starts_on' => '2026-12-25', 'ends_on' => '2026-12-26', 'periods' => []],
            ['name' => 'New Year\'s Eve', 'starts_on' => '2026-12-31', 'ends_on' => null, 'periods' => [
                ['opens_at' => '18:00', 'closes_at' => '02:00', 'label' => null],
            ]],
        ],
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->company = OrganisationUnit::factory()->company()->create();
    $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
});

test('a location is saved with its type, areas, hours and exceptions', function () {
    $this->post(route('admin.crm.organisation-units.store'), locationFormData($this->company))
        ->assertRedirect(route('admin.crm.organisation-units.index'));

    $location = OrganisationUnit::query()->where('name', 'The Groomer')->sole();

    expect($location->location_type)->toBe(LocationType::Mobile)
        ->and($location->timezone)->toBe('Europe/London')
        ->and($location->service_areas)->toBe(['M1', 'SK1'])
        ->and($location->openingHours()->pluck('label')->all())->toEqualCanonicalizing(['Lunch', 'Dinner']);

    $newYearsEve = $location->openingHourExceptions()->where('name', 'New Year\'s Eve')->sole();
    $christmas = $location->openingHourExceptions()->where('name', 'Christmas')->sole();

    expect($christmas->isClosed())->toBeTrue()
        ->and($christmas->ends_on->toDateString())->toBe('2026-12-26')
        ->and($newYearsEve->isClosed())->toBeFalse()
        ->and($newYearsEve->ends_on->toDateString())->toBe('2026-12-31');
});

test('saving replaces the hours and exceptions, and the edit page shows them', function () {
    $location = OrganisationUnit::factory()->location($this->company)->create();
    OpeningHour::factory()->for($location, 'organisationUnit')->create(['day_of_week' => 7]);
    OpeningHourException::factory()->for($location, 'organisationUnit')->create();

    $this->put(route('admin.crm.organisation-units.update', $location), locationFormData($this->company, ['slug' => $location->slug]))
        ->assertRedirect();

    expect($location->openingHours()->where('day_of_week', 7)->exists())->toBeFalse()
        ->and($location->openingHours()->count())->toBe(2)
        ->and($location->openingHourExceptions()->count())->toBe(2);

    $this->get(route('admin.crm.organisation-units.edit', $location))
        ->assertInertia(fn (Assert $page) => $page
            ->where('organisationUnit.data.location_type', 'mobile')
            ->has('organisationUnit.data.opening_hours', 2)
            ->where('organisationUnit.data.opening_hours.0.opens_at', '12:00')
            ->has('organisationUnit.data.opening_hour_exceptions', 2)
            ->has('locationTypes', count(LocationType::cases()))
        );
});

test('only a mobile location keeps service areas, and a location type defaults to premises', function () {
    $this->post(route('admin.crm.organisation-units.store'), locationFormData($this->company, ['location_type' => 'online']))->assertRedirect();
    $this->post(route('admin.crm.organisation-units.store'), locationFormData($this->company, ['location_type' => null, 'name' => 'Shop']))->assertRedirect();

    expect(OrganisationUnit::query()->where('name', 'The Groomer')->sole()->service_areas)->toBeNull()
        ->and(OrganisationUnit::query()->where('name', 'Shop')->sole()->location_type)->toBe(LocationType::Premises);
});

test('a unit that stops being a location loses its location details', function () {
    $location = OrganisationUnit::factory()->mobile($this->company)->create();
    OpeningHour::factory()->for($location, 'organisationUnit')->create();

    $this->put(route('admin.crm.organisation-units.update', $location), [
        'type' => 'company',
        'name' => $location->name,
        'email' => $location->email,
        'address' => null,
        'opening_hours' => [['day_of_week' => 1, 'opens_at' => 'nonsense', 'closes_at' => '17:00']],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $location->refresh();

    expect($location->location_type)->toBeNull()
        ->and($location->timezone)->toBeNull()
        ->and($location->service_areas)->toBeNull()
        ->and($location->openingHours()->exists())->toBeFalse();
});

test('deleting a location deletes its hours', function () {
    $location = OrganisationUnit::factory()->location($this->company)->create();
    OpeningHour::factory()->for($location, 'organisationUnit')->create();
    OpeningHourException::factory()->for($location, 'organisationUnit')->create();

    $this->delete(route('admin.crm.organisation-units.destroy', $location))->assertRedirect();

    expect(OpeningHour::query()->exists())->toBeFalse()
        ->and(OpeningHourException::query()->exists())->toBeFalse();
});

test('bad hours are rejected', function (array $overrides, string $errorKey) {
    $this->post(route('admin.crm.organisation-units.store'), locationFormData($this->company, $overrides))
        ->assertSessionHasErrors($errorKey);

    expect(OrganisationUnit::query()->where('name', 'The Groomer')->exists())->toBeFalse();
})->with([
    'an unknown location type' => [['location_type' => 'teleport'], 'location_type'],
    'an unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'a day that does not exist' => [['opening_hours' => [['day_of_week' => 8, 'opens_at' => '09:00', 'closes_at' => '17:00']]], 'opening_hours.0.day_of_week'],
    'a time that is not a time' => [['opening_hours' => [['day_of_week' => 1, 'opens_at' => '25:00', 'closes_at' => '17:00']]], 'opening_hours.0.opens_at'],
    'overlapping periods' => [['opening_hours' => [
        ['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '13:00'],
        ['day_of_week' => 1, 'opens_at' => '12:00', 'closes_at' => '17:00'],
    ]], 'opening_hours'],
    'a period after one running past midnight' => [['opening_hours' => [
        ['day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '02:00'],
        ['day_of_week' => 1, 'opens_at' => '20:00', 'closes_at' => '22:00'],
    ]], 'opening_hours'],
    'opening and closing at the same time' => [['opening_hours' => [['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '09:00']]], 'opening_hours'],
    'an end before the start' => [['opening_hour_exceptions' => [['name' => 'Closed', 'starts_on' => '2026-12-25', 'ends_on' => '2026-12-24']]], 'opening_hour_exceptions.0.ends_on'],
    'a nameless exception' => [['opening_hour_exceptions' => [['name' => '', 'starts_on' => '2026-12-25']]], 'opening_hour_exceptions.0.name'],
    'exceptions on the same date' => [['opening_hour_exceptions' => [
        ['name' => 'One', 'starts_on' => '2026-12-25', 'ends_on' => '2026-12-27'],
        ['name' => 'Two', 'starts_on' => '2026-12-27'],
    ]], 'opening_hour_exceptions'],
]);

test('the same periods on different days do not overlap', function () {
    $this->post(route('admin.crm.organisation-units.store'), locationFormData($this->company, ['opening_hours' => [
        ['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00'],
        ['day_of_week' => 2, 'opens_at' => '09:00', 'closes_at' => '17:00'],
    ]]))->assertSessionHasNoErrors();
});

test('users who can only view cannot change hours', function () {
    $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewOrganisationUnits]));
    $location = OrganisationUnit::factory()->location($this->company)->create();

    $this->put(route('admin.crm.organisation-units.update', $location), locationFormData($this->company))->assertForbidden();

    $this->get(route('admin.crm.organisation-units.show', $location))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('organisationUnit.data.opening_hours'));
});

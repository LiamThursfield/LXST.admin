<?php

use App\Models\Crm\OpeningHour;
use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use App\Services\Crm\LocationHours;
use Carbon\CarbonImmutable;

/**
 * @param  list<array{0: int, 1: string, 2: string}>  $periods
 */
function locationWithHours(array $periods, array $attributes = []): OrganisationUnit
{
    $location = OrganisationUnit::factory()->location()->create(['timezone' => 'Europe/London', ...$attributes]);

    foreach ($periods as [$day, $opens, $closes]) {
        OpeningHour::factory()->for($location, 'organisationUnit')->create([
            'day_of_week' => $day,
            'opens_at' => $opens,
            'closes_at' => $closes,
        ]);
    }

    return $location;
}

/** 2026-11-02 is a Monday, in UTC+0 for London */
function statusAt(OrganisationUnit $location, string $moment): array
{
    return app(LocationHours::class)->status($location->fresh(), CarbonImmutable::parse($moment));
}

describe('open now', function () {
    test('split shifts are open during each, and closed between them', function () {
        $restaurant = locationWithHours([[1, '12:00', '14:00'], [1, '18:00', '22:00']]);

        expect(statusAt($restaurant, '2026-11-02 12:30'))
            ->open_now->toBeTrue()
            ->closes_at->toBe('2026-11-02T14:00:00+00:00');

        expect(statusAt($restaurant, '2026-11-02 15:00'))
            ->open_now->toBeFalse()
            ->opens_at->toBe('2026-11-02T18:00:00+00:00');

        expect(statusAt($restaurant, '2026-11-02 14:00')['open_now'])->toBeFalse();
    });

    test('a period running past midnight is still open on the next day, even if that day is closed', function () {
        $bar = locationWithHours([[5, '18:00', '02:00']]);
        $saturday = '2026-11-07';
        OpeningHourException::factory()->for($bar, 'organisationUnit')->create(['starts_on' => $saturday, 'ends_on' => $saturday]);

        expect(statusAt($bar, '2026-11-07 01:00'))
            ->open_now->toBeTrue()
            ->closes_at->toBe('2026-11-07T02:00:00+00:00');

        expect(statusAt($bar, '2026-11-07 03:00')['open_now'])->toBeFalse();
    });

    test('a location open all week has no closing time', function () {
        $gym = locationWithHours(array_map(fn (int $day) => [$day, '00:00', '00:00'], range(1, 7)));

        expect(statusAt($gym, '2026-11-04 03:00'))
            ->open_now->toBeTrue()
            ->closes_at->toBeNull();
    });

    test('it uses the location\'s own clock, not the server\'s', function () {
        $newYork = locationWithHours([[1, '09:00', '17:00']], ['timezone' => 'America/New_York']);

        expect(statusAt($newYork, '2026-11-02 10:00')['open_now'])->toBeFalse()
            ->and(statusAt($newYork, '2026-11-02 15:00')['open_now'])->toBeTrue();
    });

    test('a closure replaces the weekly hours, and the next opening is found after it', function () {
        $holidayLet = locationWithHours([[1, '09:00', '17:00']]);
        OpeningHourException::factory()->for($holidayLet, 'organisationUnit')->create([
            'starts_on' => '2026-11-02',
            'ends_on' => '2027-03-01',
        ]);

        expect(statusAt($holidayLet, '2026-11-02 10:00'))
            ->open_now->toBeFalse()
            ->opens_at->toBe('2027-03-08T09:00:00+00:00');
    });

    test('special hours replace the weekly hours', function () {
        $shop = locationWithHours([[1, '09:00', '17:00']]);
        OpeningHourException::factory()->for($shop, 'organisationUnit')->specialHours([
            ['opens_at' => '18:00', 'closes_at' => '21:00', 'label' => 'Late opening'],
        ])->create(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-02']);

        expect(statusAt($shop, '2026-11-02 10:00')['open_now'])->toBeFalse();

        expect(statusAt($shop, '2026-11-02 19:00'))
            ->open_now->toBeTrue()
            ->today->toBe([['opens_at' => '18:00', 'closes_at' => '21:00', 'label' => 'Late opening']]);
    });

    test('it doesn\'t apply by appointment, or without any hours', function () {
        $byAppointment = OrganisationUnit::factory()->byAppointment()->create();
        OpeningHour::factory()->for($byAppointment, 'organisationUnit')->create();

        expect(statusAt($byAppointment, '2026-11-02 10:00')['open_now'])->toBeNull()
            ->and(statusAt(locationWithHours([]), '2026-11-02 10:00')['open_now'])->toBeNull();
    });
});

describe('schema.org', function () {
    test('days with the same hours are grouped, and open all day ends at 23:59', function () {
        $location = locationWithHours([[1, '09:00', '17:00'], [2, '09:00', '17:00'], [7, '00:00', '00:00']]);

        expect(app(LocationHours::class)->schemaOrg($location->fresh())['openingHoursSpecification'])->toBe([
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday'], 'opens' => '09:00', 'closes' => '17:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Sunday'], 'opens' => '00:00', 'closes' => '23:59'],
        ]);
    });

    test('upcoming exceptions are special hours, and past ones are left out', function () {
        $location = locationWithHours([]);
        OpeningHourException::factory()->for($location, 'organisationUnit')->create(['starts_on' => '2026-12-25', 'ends_on' => '2026-12-26']);
        OpeningHourException::factory()->for($location, 'organisationUnit')->specialHours()->create(['starts_on' => '2026-12-31', 'ends_on' => '2026-12-31']);
        OpeningHourException::factory()->for($location, 'organisationUnit')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-01-01']);

        $schemaOrg = app(LocationHours::class)->schemaOrg($location->fresh(), CarbonImmutable::parse('2026-11-02'));

        expect($schemaOrg['specialOpeningHoursSpecification'])->toBe([
            ['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-25', 'validThrough' => '2026-12-26', 'opens' => '00:00', 'closes' => '00:00'],
            ['@type' => 'OpeningHoursSpecification', 'validFrom' => '2026-12-31', 'validThrough' => '2026-12-31', 'opens' => '10:00', 'closes' => '14:00'],
        ]);
    });

    test('by appointment there is nothing to publish', function () {
        $location = OrganisationUnit::factory()->byAppointment()->create();
        OpeningHour::factory()->for($location, 'organisationUnit')->create();

        expect(app(LocationHours::class)->schemaOrg($location->fresh())['openingHoursSpecification'])->toBe([]);
    });
});

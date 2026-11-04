<?php

use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Shift;
use App\Models\Bookings\TimeOff;
use App\Models\Bookings\WorkingHour;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\ResourceSchedule;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A Monday
    $this->travelTo('2026-11-02 12:00:00');
});

describe('the schedule', function () {
    beforeEach(function () {
        $this->person = BookableResource::factory()->create();
        WorkingHour::factory()->for($this->person, 'resource')->create(['day_of_week' => 1]);
        $this->monday = CarbonImmutable::parse('2026-11-02');
    });

    test('a date uses the weekly hours', function () {
        expect(app(ResourceSchedule::class)->on($this->person, $this->monday))->toBe([
            'source' => ResourceSchedule::SOURCE_WEEKLY,
            'periods' => [['starts_at' => '09:00', 'ends_at' => '17:00', 'note' => null]],
        ]);

        expect(app(ResourceSchedule::class)->on($this->person, $this->monday->addDay())['periods'])->toBe([]);
    });

    test('shifts replace the weekly hours on their date only', function () {
        Shift::factory()->for($this->person, 'resource')->create(['works_on' => '2026-11-02', 'starts_at' => '12:00', 'ends_at' => '14:00']);
        $schedule = app(ResourceSchedule::class);

        expect($schedule->on($this->person, $this->monday)['source'])->toBe(ResourceSchedule::SOURCE_ROTA)
            ->and($schedule->on($this->person, $this->monday)['periods'][0]['starts_at'])->toBe('12:00')
            ->and($schedule->on($this->person, $this->monday->addWeek())['source'])->toBe(ResourceSchedule::SOURCE_WEEKLY);
    });

    test('only approved time off stops work, even with a shift', function (string $state, string $source) {
        Shift::factory()->for($this->person, 'resource')->create(['works_on' => '2026-11-02']);
        TimeOff::factory()->for($this->person, 'resource')->{$state}()->create(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-03']);

        expect(app(ResourceSchedule::class)->on($this->person, $this->monday)['source'])->toBe($source);
    })->with([
        'approved' => ['approved', ResourceSchedule::SOURCE_OFF],
        'declined' => ['declined', ResourceSchedule::SOURCE_ROTA],
    ]);

    test('minutes count split days, nights past midnight and whole days', function () {
        Shift::factory()->for($this->person, 'resource')->create(['works_on' => '2026-11-03', 'starts_at' => '18:00', 'ends_at' => '02:00']);
        Shift::factory()->for($this->person, 'resource')->create(['works_on' => '2026-11-04', 'starts_at' => '00:00', 'ends_at' => '00:00']);

        expect(app(ResourceSchedule::class)->minutesBetween($this->person, $this->monday, $this->monday->addDays(2)))
            ->toBe((8 + 8 + 24) * 60);
    });
});

describe('the rota page', function () {
    test('it shows a week from Monday, with time off as off and not why', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewResources]));
        $person = BookableResource::factory()->create();
        BookableResource::factory()->inactive()->create();
        TimeOff::factory()->for($person, 'resource')->approved()->sickness()->create(['starts_on' => '2026-11-04', 'ends_on' => '2026-11-04']);

        $this->get(route('admin.bookings.rota.index', ['week' => '2026-11-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/bookings/rota/Index')
                ->where('week', '2026-11-02')
                ->has('days', 7)
                ->has('resources', 1)
                ->where('resources.0.schedule.2026-11-04', ['source' => 'off', 'periods' => []])
                ->where('canManage', false)
            )
            ->assertDontSee('Unwell');
    });

    test('shifts need the manage permission', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewResources]));
        $person = BookableResource::factory()->create();

        $this->put(route('admin.bookings.rota.update'), ['resource_id' => $person->id, 'date' => '2026-11-02', 'shifts' => []])
            ->assertForbidden();
    });

    test('saving replaces a date\'s shifts, and none goes back to the usual hours', function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
        $person = BookableResource::factory()->create();
        $other = Shift::factory()->for($person, 'resource')->create(['works_on' => '2026-11-03']);
        Shift::factory()->for($person, 'resource')->create(['works_on' => '2026-11-02']);

        $this->put(route('admin.bookings.rota.update'), [
            'resource_id' => $person->id,
            'date' => '2026-11-02',
            'shifts' => [
                ['starts_at' => '08:00', 'ends_at' => '12:00', 'note' => 'Early'],
                ['starts_at' => '13:00', 'ends_at' => '15:00'],
            ],
        ])->assertSessionHasNoErrors();

        expect($person->shifts()->whereDate('works_on', '2026-11-02')->pluck('starts_at')->sort()->values()->all())->toBe(['08:00', '13:00']);

        $this->put(route('admin.bookings.rota.update'), ['resource_id' => $person->id, 'date' => '2026-11-02', 'shifts' => []])
            ->assertSessionHasNoErrors();

        expect($person->shifts()->pluck('id')->all())->toBe([$other->id]);
    });

    test('overlapping shifts are refused', function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
        $person = BookableResource::factory()->create();

        $this->put(route('admin.bookings.rota.update'), [
            'resource_id' => $person->id,
            'date' => '2026-11-02',
            'shifts' => [
                ['starts_at' => '08:00', 'ends_at' => '12:00'],
                ['starts_at' => '11:00', 'ends_at' => '15:00'],
            ],
        ])->assertSessionHasErrors('shifts');
    });
});

describe('the hours report', function () {
    test('it needs its own permission', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewResources]));

        $this->get(route('admin.bookings.staff-hours.index'))->assertForbidden();
    });

    test('it totals people\'s hours less time off, and counts only days in the range', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewStaffReports]));
        $person = BookableResource::factory()->create(['name' => 'Sam']);
        BookableResource::factory()->space()->create();

        foreach ([1, 2, 3] as $day) {
            WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => $day]);
        }

        TimeOff::factory()->for($person, 'resource')->approved()->create(['starts_on' => '2026-10-30', 'ends_on' => '2026-11-02']);

        $this->get(route('admin.bookings.staff-hours.index', ['from' => '2026-11-02', 'to' => '2026-11-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('people', 1)
                ->where('people.0.name', 'Sam')
                ->where('people.0.hours', 16)
                ->where('people.0.days_off', 1)
            );
    });

    test('a range is limited to a year', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewStaffReports]));

        $this->get(route('admin.bookings.staff-hours.index', ['from' => '2026-01-01', 'to' => '2028-01-01']))
            ->assertSessionHasErrors('to');
    });
});

<?php

use App\Data\Bookings\BookingSlot;
use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\ResourceType;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceOption;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\Shift;
use App\Models\Bookings\TimeOff;
use App\Models\Bookings\WorkingHour;
use App\Models\Crm\OpeningHour;
use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use App\Services\Bookings\BookingAvailability;
use App\Services\Bookings\BookingCreator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

const NEXT_MONDAY = '2026-11-09';

beforeEach(function () {
    // A Monday, a week before NEXT_MONDAY
    $this->travelTo('2026-11-02 12:00:00');
    $this->availability = app(BookingAvailability::class);
});

/**
 * A person who can do the service, working from nine to five on Mondays
 */
function personFor(Service $service, array $attributes = [], array $pivot = []): BookableResource
{
    $person = BookableResource::factory()->create($attributes);
    $person->services()->attach($service, $pivot);
    WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => 1]);

    return $person;
}

/**
 * @param  list<BookingSlot>  $slots
 * @return list<string>
 */
function times(array $slots): array
{
    return array_map(fn (BookingSlot $slot) => $slot->startsAt->format('H:i'), $slots);
}

describe('a time-based service', function () {
    test('it offers times every 15 minutes that fit within the working hours', function () {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        personFor($service);

        $times = times($this->availability->slots($service, null, NEXT_MONDAY));

        expect($times[0])->toBe('09:00')
            ->and($times[1])->toBe('09:15')
            ->and(end($times))->toBe('16:00');
    });

    test('nothing is offered with nobody able to do it, or nobody working', function () {
        $service = Service::factory()->create();

        expect($this->availability->slots($service, null, NEXT_MONDAY))->toBe([]);

        personFor($service);

        expect($this->availability->slots($service, null, '2026-11-10'))->toBe([]);
    });

    test('an inactive resource, or one on time off, is not offered', function () {
        $service = Service::factory()->create();
        $person = personFor($service);
        TimeOff::factory()->for($person, 'resource')->approved()->create(['starts_on' => NEXT_MONDAY, 'ends_on' => NEXT_MONDAY]);
        personFor($service, ['is_active' => false]);

        expect($this->availability->slots($service, null, NEXT_MONDAY))->toBe([]);
    });

    test('a shift replaces the working hours for that date', function () {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $person = personFor($service);
        Shift::factory()->for($person, 'resource')->create(['works_on' => NEXT_MONDAY, 'starts_at' => '13:00', 'ends_at' => '14:00']);

        expect(times($this->availability->slots($service, null, NEXT_MONDAY)))->toBe(['13:00']);
    });

    test('past times are not offered', function () {
        $this->travelTo(NEXT_MONDAY.' 15:50:00');
        $service = Service::factory()->create(['duration_minutes' => 60]);
        personFor($service);

        expect(times($this->availability->slots($service, null, NEXT_MONDAY)))->toBe(['16:00']);
    });

    test('a booking, with its buffers, takes those times away', function () {
        $service = Service::factory()->create(['duration_minutes' => 60, 'buffer_before_minutes' => 15, 'buffer_after_minutes' => 30]);
        $person = personFor($service);
        $booking = Booking::factory()->for($service)->create([
            'blocked_from' => NEXT_MONDAY.' 12:00:00',
            'blocked_until' => NEXT_MONDAY.' 13:00:00',
        ]);
        $booking->resources()->attach($person);

        $times = times($this->availability->slots($service, null, NEXT_MONDAY));

        // A new booking's buffers can't touch it: it has to end, buffer included, by 12:00, or start, buffer included, from 13:00
        expect($times)->toContain('10:30')->not->toContain('10:45')
            ->and($times)->not->toContain('13:00')->toContain('13:15');
    });

    test('cancelled and no-show bookings free up their time', function (BookingStatus $status) {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $person = personFor($service);
        $booking = Booking::factory()->for($service)->status($status)->create([
            'blocked_from' => NEXT_MONDAY.' 12:00:00',
            'blocked_until' => NEXT_MONDAY.' 13:00:00',
        ]);
        $booking->resources()->attach($person);

        expect(times($this->availability->slots($service, null, NEXT_MONDAY)))->toContain('12:00');
    })->with([
        'cancelled' => [BookingStatus::Cancelled],
        'no-show' => [BookingStatus::NoShow],
    ]);

    test('a session a resource is on takes those times away', function () {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $person = personFor($service);
        ServiceSession::factory()->create(['starts_at' => NEXT_MONDAY.' 12:00:00', 'ends_at' => NEXT_MONDAY.' 13:00:00'])->resources()->attach($person);

        expect(times($this->availability->slots($service, null, NEXT_MONDAY)))->not->toContain('12:00');
    });

    test('another free person is used when the first is booked, and a preference is kept', function () {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $alex = personFor($service, ['name' => 'Alex']);
        $sam = personFor($service, ['name' => 'Sam']);
        $booking = Booking::factory()->for($service)->create([
            'blocked_from' => NEXT_MONDAY.' 09:00:00',
            'blocked_until' => NEXT_MONDAY.' 10:00:00',
        ]);
        $booking->resources()->attach($alex);
        $start = CarbonImmutable::parse(NEXT_MONDAY.' 09:00');

        expect($this->availability->slotAt($service, null, $start)->resources[0]->id)->toBe($sam->id)
            ->and($this->availability->slotAt($service, null, $start, 1, [], [$alex->id]))->toBeNull();
    });

    test('a person\'s own length and any options change how long it takes', function () {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        personFor($service, [], ['duration_minutes' => 30]);
        $longer = ServiceOption::factory()->for($service)->create(['duration_adjustment' => 15]);

        $slot = $this->availability->slotAt($service, null, CarbonImmutable::parse(NEXT_MONDAY.' 09:00'), 1, [$longer->id]);

        expect($slot->endsAt->format('H:i'))->toBe('09:45');
    });

    test('a service needing two kinds of resource needs both free', function () {
        $service = Service::factory()->needing([ResourceType::Person, ResourceType::Space])->create(['duration_minutes' => 60]);
        $person = personFor($service, ['name' => 'Sam']);
        $start = CarbonImmutable::parse(NEXT_MONDAY.' 09:00');

        expect($this->availability->slotAt($service, null, $start))->toBeNull();

        $chair = BookableResource::factory()->space()->create();
        $chair->services()->attach($service);

        expect(array_map(fn (BookableResource $resource) => $resource->id, $this->availability->slotAt($service, null, $start)->resources))
            ->toBe([$person->id, $chair->id]);

        Booking::factory()->for($service)->create(['blocked_from' => NEXT_MONDAY.' 09:00:00', 'blocked_until' => NEXT_MONDAY.' 10:00:00'])->resources()->attach($chair);

        expect($this->availability->slotAt($service, null, $start))->toBeNull();
    });

    test('a visit blocks its travel time beforehand, without that needing to be in working hours', function () {
        $service = Service::factory()->visit(30)->create(['duration_minutes' => 60]);
        personFor($service);

        $slot = $this->availability->slotAt($service, null, CarbonImmutable::parse(NEXT_MONDAY.' 09:00'));

        expect($slot->blockedFrom->format('H:i'))->toBe('08:30')
            ->and($slot->startsAt->format('H:i'))->toBe('09:00');
    });
});

describe('locations', function () {
    test('times are only within opening hours, in the location\'s timezone', function () {
        $location = OrganisationUnit::factory()->location()->create(['timezone' => 'America/New_York']);
        OpeningHour::factory()->for($location, 'organisationUnit')->create(['day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '12:00']);
        $service = Service::factory()->create(['duration_minutes' => 60]);
        personFor($service);

        $slots = $this->availability->slots($service, $location->load(['openingHours', 'openingHourExceptions']), NEXT_MONDAY);

        expect(times($slots))->toBe(['10:00', '10:15', '10:30', '10:45', '11:00'])
            ->and($slots[0]->startsAt->utc()->format('H:i'))->toBe('15:00');
    });

    test('a closure takes the day away', function () {
        $location = OrganisationUnit::factory()->location()->create();
        OpeningHour::factory()->for($location, 'organisationUnit')->create(['day_of_week' => 1]);
        OpeningHourException::factory()->for($location, 'organisationUnit')->create(['starts_on' => NEXT_MONDAY, 'ends_on' => NEXT_MONDAY]);
        $service = Service::factory()->create();
        personFor($service);

        expect($this->availability->slots($service, $location, NEXT_MONDAY))->toBe([]);
    });

    test('a location with no hours is treated as open', function () {
        $location = OrganisationUnit::factory()->location()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        personFor($service);

        expect(times($this->availability->slots($service, $location, NEXT_MONDAY))[0])->toBe('09:00');
    });

    test('only resources at the location, or tied to none, are used', function () {
        $here = OrganisationUnit::factory()->location()->create();
        $there = OrganisationUnit::factory()->location()->create();
        $service = Service::factory()->create();
        personFor($service, ['location_id' => $there->id]);

        expect($this->availability->slots($service, $here, NEXT_MONDAY))->toBe([]);

        personFor($service, ['location_id' => null]);

        expect($this->availability->slots($service, $here, NEXT_MONDAY))->not->toBe([]);
    });

    test('a night running past midnight can be booked after midnight, on the next date', function () {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $person = BookableResource::factory()->create();
        $person->services()->attach($service);
        WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => 1, 'starts_at' => '22:00', 'ends_at' => '02:00']);

        // The windows run from the Monday night into the Tuesday, whose date is asked for here
        expect(times($this->availability->slots($service, null, '2026-11-10')))->toContain('01:00')->not->toContain('01:15');
    });
});

describe('tables', function () {
    test('the smallest table that fits is used, and none if the party is too big', function () {
        $service = Service::factory()->table(1, 20)->create(['duration_minutes' => 90]);

        foreach ([8, 2, 4] as $seats) {
            BookableResource::factory()->space($seats)->create()->services()->attach($service);
        }

        $start = CarbonImmutable::parse(NEXT_MONDAY.' 12:00');

        expect($this->availability->slotAt($service, null, $start, 3)->resources[0]->capacity)->toBe(4)
            ->and($this->availability->slotAt($service, null, $start, 9))->toBeNull();
    });
});

describe('hires', function () {
    test('by the hour, the length follows the hours taken', function () {
        $service = Service::factory()->hire()->create();
        BookableResource::factory()->space()->create()->services()->attach($service);

        expect($this->availability->slotAt($service, null, CarbonImmutable::parse(NEXT_MONDAY.' 10:00'), 3)->endsAt->format('H:i'))->toBe('13:00');
    });

    test('by the day, it offers one start, blocked for each day, and needs them all free', function () {
        $service = Service::factory()->hire(HireUnit::Day)->create();
        $van = BookableResource::factory()->vehicle()->create();
        $van->services()->attach($service);
        $service->update(['required_resource_types' => [ResourceType::Vehicle->value]]);

        $slots = $this->availability->slots($service, null, NEXT_MONDAY, 2);

        expect(times($slots))->toBe(['09:00'])
            ->and($slots[0]->endsAt->toDateTimeString())->toBe('2026-11-11 09:00:00');

        TimeOff::factory()->for($van, 'resource')->approved()->create(['starts_on' => '2026-11-10', 'ends_on' => '2026-11-10']);

        expect($this->availability->slots($service->fresh(), null, NEXT_MONDAY, 2))->toBe([])
            ->and(times($this->availability->slots($service->fresh(), null, NEXT_MONDAY, 1)))->toBe(['09:00']);
    });
});

describe('sessions', function () {
    test('only upcoming sessions with enough places are offered', function () {
        $service = Service::factory()->groupClass(5)->create();
        $open = ServiceSession::factory()->for($service)->create();
        ServiceSession::factory()->for($service)->past()->create();
        Booking::factory()->for($service)->create(['session_id' => $open->id, 'quantity' => 3]);
        Booking::factory()->for($service)->status(BookingStatus::Cancelled)->create(['session_id' => $open->id, 'quantity' => 2]);

        expect($this->availability->placesLeft($open))->toBe(2)
            ->and($this->availability->sessions($service, null, 2)->pluck('id')->all())->toBe([$open->id])
            ->and($this->availability->sessions($service, null, 3)->all())->toBe([]);
    });

    test('a session\'s own capacity beats the service\'s', function () {
        $session = ServiceSession::factory()->withCapacity(3)->for(Service::factory()->groupClass(20))->create();

        expect($this->availability->placesLeft($session))->toBe(3);
    });
});

test('the same time cannot be booked twice', function () {
    $service = Service::factory()->create(['duration_minutes' => 60]);
    personFor($service);
    $details = ['customer_name' => 'Morgan', 'date' => NEXT_MONDAY, 'time' => '09:00'];

    app(BookingCreator::class)->create($service, null, $details);

    expect(fn () => app(BookingCreator::class)->create($service, null, $details))
        ->toThrow(ValidationException::class);

    expect(Booking::query()->count())->toBe(1);
});

<?php

use App\Enums\Bookings\BookingStatus;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\TimeOff;
use App\Models\Bookings\WorkingHour;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\BookingsPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A Monday
    $this->travelTo('2026-11-09 08:00:00');
    $this->service = Service::factory()->create(['duration_minutes' => 60, 'buffer_after_minutes' => 15]);
    $this->sam = BookableResource::factory()->create(['name' => 'Sam']);
    $this->alex = BookableResource::factory()->create(['name' => 'Alex']);

    foreach ([$this->sam, $this->alex] as $person) {
        $person->services()->attach($this->service);
        WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => 1]);
    }
});

function bookWith(BookableResource $resource, Service $service, string $start, string $end, array $attributes = []): Booking
{
    $booking = Booking::factory()->for($service)->create([
        'starts_at' => $start,
        'ends_at' => $end,
        'blocked_from' => $start,
        'blocked_until' => $end,
        ...$attributes,
    ]);
    $booking->resources()->attach($resource);

    return $booking;
}

describe('the calendar', function () {
    test('it needs the view permission', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.bookings.calendar.index'))->assertForbidden();
    });

    test('a day shows each resource with its hours and bookings, in minutes from midnight', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));
        bookWith($this->sam, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:30:00', ['customer_name' => 'Morgan']);
        bookWith($this->sam, $this->service, '2026-11-09 12:00:00', '2026-11-09 13:00:00')->update(['status' => BookingStatus::Cancelled]);
        TimeOff::factory()->for($this->alex, 'resource')->approved()->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09']);

        $this->get(route('admin.bookings.calendar.index', ['date' => '2026-11-09']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/bookings/calendar/Index')
                ->where('view', 'day')
                ->has('columns', 2)
                ->where('columns.0.resource_name', 'Alex')
                ->where('columns.0.off', true)
                ->where('columns.1.resource_name', 'Sam')
                ->where('columns.1.periods', [['start' => 540, 'end' => 1020]])
                ->has('columns.1.items', 1)
                ->where('columns.1.items.0.title', 'Morgan')
                ->where('columns.1.items.0.start', 600)
                ->where('columns.1.items.0.end', 690)
                ->where('columns.1.items.0.movable', true)
                ->where('startMinutes', 480)
                ->where('endMinutes', 1080)
                ->where('canManage', false)
            );
    });

    test('a week shows one resource\'s seven days from Monday', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));
        bookWith($this->alex, $this->service, '2026-11-11 10:00:00', '2026-11-11 11:00:00');

        $this->get(route('admin.bookings.calendar.index', ['view' => 'week', 'date' => '2026-11-12', 'resource_id' => $this->alex->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('date', '2026-11-09')
                ->where('resourceId', $this->alex->id)
                ->has('columns', 7)
                ->where('columns.2.date', '2026-11-11')
                ->has('columns.2.items', 1)
                ->has('columns.0.items', 0)
            );
    });

    test('times follow the chosen location\'s timezone, and only its resources are shown', function () {
        $location = OrganisationUnit::factory()->location()->create(['timezone' => 'America/New_York']);
        $this->sam->update(['location_id' => $location->id]);
        BookableResource::factory()->create(['location_id' => OrganisationUnit::factory()->location()->create()->id]);
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));
        bookWith($this->sam, $this->service, '2026-11-09 15:00:00', '2026-11-09 16:00:00');

        $this->get(route('admin.bookings.calendar.index', ['date' => '2026-11-09', 'location_id' => $location->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('timezone', 'America/New_York')
                ->has('columns', 2)
                ->where('columns.1.items.0.start', 600)
            );
    });

    test('a session shows on its resources with how many are booked, and can\'t be dragged', function () {
        $session = ServiceSession::factory()->create(['starts_at' => '2026-11-09 18:00:00', 'ends_at' => '2026-11-09 19:00:00']);
        $session->resources()->attach($this->sam);
        Booking::factory()->for($session->service)->create(['session_id' => $session->id, 'quantity' => 3]);
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));

        $this->get(route('admin.bookings.calendar.index', ['date' => '2026-11-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns.1.items.0.kind', 'session')
                ->where('columns.1.items.0.booked', 3)
                ->where('columns.1.items.0.movable', false)
                ->where('endMinutes', 1140)
            );
    });
});

describe('moving a booking', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
    });

    test('it keeps the length and buffers, and can swap to another resource of the same type', function () {
        $booking = bookWith($this->sam, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:00:00', ['blocked_until' => '2026-11-09 11:15:00']);

        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '14:30', 'resource_id' => $this->alex->id])
            ->assertSessionHasNoErrors();

        $booking->refresh();

        expect($booking->starts_at->format('H:i'))->toBe('14:30')
            ->and($booking->ends_at->format('H:i'))->toBe('15:30')
            ->and($booking->blocked_until->format('H:i'))->toBe('15:45')
            ->and($booking->resources->pluck('id')->all())->toBe([$this->alex->id]);
    });

    test('a clash is refused, but its own old time isn\'t a clash', function () {
        $booking = bookWith($this->sam, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:00:00');
        bookWith($this->sam, $this->service, '2026-11-09 14:00:00', '2026-11-09 15:00:00');

        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '13:30'])->assertSessionHasErrors('time');
        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '10:30'])->assertSessionHasNoErrors();

        expect($booking->fresh()->starts_at->format('H:i'))->toBe('10:30');
    });

    test('it can be moved out of hours, as only clashes are checked', function () {
        $booking = bookWith($this->sam, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:00:00');

        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '20:00'])->assertSessionHasNoErrors();
    });

    test('it can\'t go to a resource that can\'t do the service, or of another type', function () {
        $booking = bookWith($this->sam, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:00:00');
        $stranger = BookableResource::factory()->create();
        $room = BookableResource::factory()->space()->create();
        $room->services()->attach($this->service);

        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '10:00', 'resource_id' => $stranger->id])->assertSessionHasErrors('resource_id');
        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '10:00', 'resource_id' => $room->id])->assertSessionHasErrors('resource_id');
    });

    test('only bookings with their own time that haven\'t started can move', function (string $kind) {
        $booking = match ($kind) {
            'checked in' => bookWith($this->sam, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:00:00', ['status' => BookingStatus::CheckedIn]),
            'on a session' => Booking::factory()->create(['session_id' => ServiceSession::factory()->create()->id]),
            'enquiry' => Booking::factory()->enquiry()->create(),
        };

        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '12:00'])
            ->assertSessionHasErrors(['booking' => 'Only bookings with a time of their own that haven\'t started can be moved.']);
    })->with(['checked in', 'on a session', 'enquiry']);

    test('it needs the manage permission', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));
        $booking = bookWith($this->sam, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:00:00');

        $this->put(route('admin.bookings.bookings.move', $booking), ['date' => '2026-11-09', 'time' => '12:00'])->assertForbidden();
    });
});

describe('walk-ins', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
    });

    test('a walk-in starts now, checked in, with whoever is free', function () {
        $this->travelTo('2026-11-09 10:07:30');
        bookWith($this->alex, $this->service, '2026-11-09 10:00:00', '2026-11-09 11:00:00');

        $this->post(route('admin.bookings.bookings.store'), ['service_id' => $this->service->id, 'walk_in' => true, 'customer_name' => 'Morgan'])
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->latest('id')->first();

        expect($booking->is_walk_in)->toBeTrue()
            ->and($booking->status)->toBe(BookingStatus::CheckedIn)
            ->and($booking->starts_at->format('H:i:s'))->toBe('10:07:00')
            ->and($booking->resources->sole()->id)->toBe($this->sam->id);
    });

    test('a walk-in is refused when nobody is free or working', function () {
        $this->travelTo('2026-11-09 20:00:00');

        $this->post(route('admin.bookings.bookings.store'), ['service_id' => $this->service->id, 'walk_in' => true, 'customer_name' => 'Morgan'])
            ->assertSessionHasErrors('time');
    });

    test('a class or enquiry can\'t be a walk-in, so still needs what it usually does', function () {
        $class = Service::factory()->groupClass()->create();

        $this->post(route('admin.bookings.bookings.store'), ['service_id' => $class->id, 'walk_in' => true, 'customer_name' => 'Morgan'])
            ->assertSessionHasErrors('session_id');
    });
});

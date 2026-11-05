<?php

use App\Enums\Bookings\BookingStatus;
use App\Enums\TenantModule;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceOption;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\WorkingHour;
use App\Models\Crm\Contact;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\BookingsPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-02 12:00:00');
});

function bookableService(array $attributes = [], array $pivot = []): Service
{
    $service = Service::factory()->create(['duration_minutes' => 60, ...$attributes]);
    $person = BookableResource::factory()->create();
    $person->services()->attach($service, $pivot);
    WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => 1]);

    return $service;
}

function bookingData(Service $service, array $overrides = []): array
{
    return [
        'service_id' => $service->id,
        'date' => '2026-11-09',
        'time' => '09:00',
        'customer_name' => 'Morgan Reed',
        'customer_email' => 'morgan@example.test',
        ...$overrides,
    ];
}

describe('access', function () {
    test('users without the view permission cannot see bookings', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.bookings.bookings.index'))->assertForbidden();
        $this->get(route('admin.bookings.sessions.index'))->assertForbidden();
    });

    test('users with only the view permission can see bookings but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));
        $booking = Booking::factory()->create();

        $this->get(route('admin.bookings.bookings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('bookings.data', 1)->where('canCreate', false));

        $this->get(route('admin.bookings.bookings.show', $booking))->assertOk();
        $this->get(route('admin.bookings.bookings.create'))->assertForbidden();
        $this->post(route('admin.bookings.bookings.store'), [])->assertForbidden();
        $this->post(route('admin.bookings.bookings.status', $booking), ['status' => 'cancelled'])->assertForbidden();
        $this->getJson(route('admin.bookings.bookings.availability', ['service_id' => $booking->service_id]))->assertForbidden();
    });

    test('the pages return not found without the Bookings module', function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Crm]]);

        $this->get(route('admin.bookings.bookings.index'))->assertNotFound();
        $this->get(route('admin.bookings.sessions.index'))->assertNotFound();
    });
});

describe('making a booking', function () {
    beforeEach(function () {
        $this->user = $this->createUserWithPermissions(BookingsPermission::cases());
        $this->actingAs($this->user);
    });

    test('it books a free time, copying the service and adding a CRM contact', function () {
        $service = bookableService(['name' => 'Haircut', 'price' => 2500]);
        $addOn = ServiceOption::factory()->for($service)->addOn()->create(['name' => 'Beard trim', 'price_adjustment' => 500, 'duration_adjustment' => 15]);

        $this->post(route('admin.bookings.bookings.store'), bookingData($service, ['option_ids' => [$addOn->id]]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $booking = Booking::query()->sole();

        expect($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->service_name)->toBe('Haircut')
            ->and($booking->price)->toBe(3000)
            ->and($booking->ends_at->format('H:i'))->toBe('10:15')
            ->and($booking->resources)->toHaveCount(1)
            ->and($booking->options->sole()->name)->toBe('Beard trim')
            ->and($booking->created_by_id)->toBe($this->user->id)
            ->and($booking->contact->email)->toBe('morgan@example.test')
            ->and($booking->contact->first_name)->toBe('Morgan')
            ->and($booking->contact->last_name)->toBe('Reed');

        $service->update(['name' => 'Renamed']);
        $addOn->delete();

        expect($booking->fresh()->service_name)->toBe('Haircut')
            ->and($booking->fresh()->options->sole()->name)->toBe('Beard trim');
    });

    test('an existing contact is used, and a deleted one is left deleted', function () {
        $contact = Contact::factory()->create(['email' => 'morgan@example.test']);

        $this->post(route('admin.bookings.bookings.store'), bookingData(bookableService()))->assertSessionHasNoErrors();

        expect(Booking::query()->sole()->contact_id)->toBe($contact->id);

        $contact->delete();

        $this->post(route('admin.bookings.bookings.store'), bookingData(bookableService()))->assertSessionHasNoErrors();

        expect(Booking::query()->latest('id')->first()->contact_id)->toBeNull()
            ->and(Contact::query()->count())->toBe(0);
    });

    test('without the CRM, the booking keeps the details with no contact', function () {
        tenant()->update(['modules' => [TenantModule::Bookings]]);

        $this->post(route('admin.bookings.bookings.store'), bookingData(bookableService()))->assertSessionHasNoErrors();

        expect(Booking::query()->sole()->contact_id)->toBeNull()
            ->and(Booking::query()->sole()->customer_email)->toBe('morgan@example.test')
            ->and(Contact::query()->count())->toBe(0);
    });

    test('a taken or out of hours time is refused, with a useful message', function () {
        $service = bookableService();

        $this->post(route('admin.bookings.bookings.store'), bookingData($service))->assertSessionHasNoErrors();
        $this->post(route('admin.bookings.bookings.store'), bookingData($service))->assertSessionHasErrors('time');
        $this->post(route('admin.bookings.bookings.store'), bookingData($service, ['time' => '20:00']))->assertSessionHasErrors('time');

        expect(Booking::query()->count())->toBe(1);
    });

    test('a resource\'s own price is used, and hires are priced per unit', function () {
        $service = bookableService(['price' => 2500], ['price' => 4000]);

        $this->post(route('admin.bookings.bookings.store'), bookingData($service))->assertSessionHasNoErrors();

        expect(Booking::query()->sole()->price)->toBe(4000);

        $hire = Service::factory()->hire()->create(['price' => 1000]);
        BookableResource::factory()->space()->create()->services()->attach($hire);

        $this->post(route('admin.bookings.bookings.store'), bookingData($hire, ['quantity' => 3]))->assertSessionHasNoErrors();

        expect(Booking::query()->latest('id')->first()->price)->toBe(3000);
    });

    test('a party or hire outside the service\'s limits is refused', function () {
        $table = Service::factory()->table(2, 6)->create();

        $this->post(route('admin.bookings.bookings.store'), bookingData($table, ['quantity' => 7]))->assertSessionHasErrors('quantity');
        $this->post(route('admin.bookings.bookings.store'), bookingData($table, ['quantity' => 1]))->assertSessionHasErrors('quantity');
    });

    test('only one variation can be chosen', function () {
        $service = bookableService();
        $options = ServiceOption::factory()->for($service)->count(2)->create();

        $this->post(route('admin.bookings.bookings.store'), bookingData($service, ['option_ids' => $options->modelKeys()]))
            ->assertSessionHasErrors('option_ids');
    });

    test('an option from another service is refused', function () {
        $other = ServiceOption::factory()->create();

        $this->post(route('admin.bookings.bookings.store'), bookingData(bookableService(), ['option_ids' => [$other->id]]))
            ->assertSessionHasErrors('option_ids.0');
    });

    test('a service not offered at the location is refused', function () {
        $service = bookableService();
        $service->locations()->attach(OrganisationUnit::factory()->location()->create());
        $elsewhere = OrganisationUnit::factory()->location()->create();

        $this->post(route('admin.bookings.bookings.store'), bookingData($service, ['location_id' => $elsewhere->id]))
            ->assertSessionHasErrors('location_id');
    });

    test('a place on a session can be booked until they run out', function () {
        $service = Service::factory()->groupClass(3)->create(['price' => 1000]);
        $session = ServiceSession::factory()->for($service)->create();
        $data = ['service_id' => $service->id, 'session_id' => $session->id, 'customer_name' => 'Morgan', 'quantity' => 2];

        $this->post(route('admin.bookings.bookings.store'), $data)->assertSessionHasNoErrors();

        $booking = Booking::query()->sole();

        expect($booking->starts_at->equalTo($session->starts_at))->toBeTrue()
            ->and($booking->price)->toBe(2000)
            ->and($booking->resources)->toHaveCount(0);

        $this->post(route('admin.bookings.bookings.store'), $data)->assertSessionHasErrors('session_id');

        expect(Booking::query()->count())->toBe(1);
    });

    test('a session of another service cannot be booked', function () {
        $service = Service::factory()->groupClass()->create();
        $other = ServiceSession::factory()->create();

        $this->post(route('admin.bookings.bookings.store'), ['service_id' => $service->id, 'session_id' => $other->id, 'customer_name' => 'Morgan'])
            ->assertSessionHasErrors('session_id');
    });

    test('an enquiry needs no time and starts as requested', function () {
        $service = Service::factory()->enquiry()->create();

        $this->post(route('admin.bookings.bookings.store'), ['service_id' => $service->id, 'customer_name' => 'Morgan', 'notes' => 'A wedding'])
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->sole();

        expect($booking->status)->toBe(BookingStatus::Requested)
            ->and($booking->needsTime())->toBeTrue()
            ->and($booking->price)->toBeNull();
    });

    test('the availability endpoint gives times or sessions', function () {
        $service = bookableService();

        $this->getJson(route('admin.bookings.bookings.availability', ['service_id' => $service->id, 'date' => '2026-11-09']))
            ->assertOk()
            ->assertJsonPath('slots.0.time', '09:00')
            ->assertJsonCount(1, 'slots.0.resources');

        $class = ServiceSession::factory()->create();

        $this->getJson(route('admin.bookings.bookings.availability', ['service_id' => $class->service_id]))
            ->assertOk()
            ->assertJsonPath('sessions.0.id', $class->id)
            ->assertJsonPath('sessions.0.places_left', 20);
    });
});

describe('statuses', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
    });

    test('a booking moves through allowed statuses only', function () {
        $booking = Booking::factory()->create();

        $this->post(route('admin.bookings.bookings.status', $booking), ['status' => 'checked_in'])->assertSessionHasNoErrors();
        expect($booking->fresh()->status)->toBe(BookingStatus::CheckedIn);

        $this->post(route('admin.bookings.bookings.status', $booking), ['status' => 'cancelled'])->assertSessionHasErrors('status');

        $this->post(route('admin.bookings.bookings.status', $booking), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->post(route('admin.bookings.bookings.status', $booking), ['status' => 'confirmed'])->assertSessionHasErrors('status');

        expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
    });

    test('an enquiry can be cancelled, but not confirmed without a time', function () {
        $enquiry = Booking::factory()->enquiry()->create();

        $this->post(route('admin.bookings.bookings.status', $enquiry), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->post(route('admin.bookings.bookings.status', $enquiry), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    });

    test('setting a time confirms an enquiry, and refuses a clash', function () {
        $enquiry = Booking::factory()->enquiry()->create();
        $van = BookableResource::factory()->vehicle()->create();
        Booking::factory()->create(['blocked_from' => '2026-11-09 09:00:00', 'blocked_until' => '2026-11-09 10:00:00'])->resources()->attach($van);
        $data = ['date' => '2026-11-09', 'time' => '09:30', 'duration_minutes' => 120, 'resource_ids' => [$van->id]];

        $this->put(route('admin.bookings.bookings.schedule', $enquiry), $data)->assertSessionHasErrors('time');

        expect($enquiry->fresh()->needsTime())->toBeTrue();

        $this->put(route('admin.bookings.bookings.schedule', $enquiry), [...$data, 'time' => '10:00'])->assertSessionHasNoErrors();

        $enquiry->refresh();

        expect($enquiry->status)->toBe(BookingStatus::Confirmed)
            ->and($enquiry->ends_at->format('H:i'))->toBe('12:00')
            ->and($enquiry->resources->sole()->id)->toBe($van->id);
    });

    test('a booking that already has a time cannot be given one this way', function () {
        $this->put(route('admin.bookings.bookings.schedule', Booking::factory()->create()), ['date' => '2026-11-09', 'time' => '10:00', 'duration_minutes' => 60])
            ->assertForbidden();
    });
});

describe('the timetable', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
    });

    function sessionData(Service $service, array $overrides = []): array
    {
        return ['service_id' => $service->id, 'date' => '2026-11-09', 'time' => '18:00', 'duration_minutes' => 45, 'resource_ids' => [], ...$overrides];
    }

    test('it adds a session in the location\'s timezone', function () {
        $location = OrganisationUnit::factory()->location()->create(['timezone' => 'America/New_York']);
        $service = Service::factory()->groupClass()->create();

        $this->post(route('admin.bookings.sessions.store'), sessionData($service, ['location_id' => $location->id]))
            ->assertRedirect(route('admin.bookings.sessions.index'));

        $session = ServiceSession::query()->sole();

        expect($session->starts_at->format('H:i'))->toBe('23:00')
            ->and($session->ends_at->format('H:i'))->toBe('23:45');
    });

    test('only classes and events can go on it', function () {
        $this->post(route('admin.bookings.sessions.store'), sessionData(Service::factory()->create()))
            ->assertSessionHasErrors('service_id');
    });

    test('a busy resource is refused', function () {
        $coach = BookableResource::factory()->create();
        Booking::factory()->create(['blocked_from' => '2026-11-09 18:00:00', 'blocked_until' => '2026-11-09 19:00:00'])->resources()->attach($coach);

        $this->post(route('admin.bookings.sessions.store'), sessionData(Service::factory()->groupClass()->create(), ['resource_ids' => [$coach->id]]))
            ->assertSessionHasErrors('resource_ids');
    });

    test('moving a session moves its bookings, and it can\'t have fewer places than are taken', function () {
        $service = Service::factory()->groupClass(10)->create();
        $session = ServiceSession::factory()->for($service)->create();
        $booking = Booking::factory()->for($service)->create(['session_id' => $session->id, 'quantity' => 4]);

        $this->put(route('admin.bookings.sessions.update', $session), sessionData($service, ['capacity' => 3]))
            ->assertSessionHasErrors('capacity');

        $this->put(route('admin.bookings.sessions.update', $session), sessionData($service, ['capacity' => 4]))
            ->assertSessionHasNoErrors();

        expect($booking->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-11-09 18:00');
    });

    test('a session with bookings can\'t be deleted, and one without can', function () {
        $service = Service::factory()->groupClass()->create();
        $booked = ServiceSession::factory()->for($service)->create();
        Booking::factory()->for($service)->create(['session_id' => $booked->id]);
        $empty = ServiceSession::factory()->for($service)->create();

        $this->delete(route('admin.bookings.sessions.destroy', $booked))->assertSessionHas('error');
        $this->delete(route('admin.bookings.sessions.destroy', $empty))->assertSessionHas('success');

        expect(ServiceSession::query()->pluck('id')->all())->toBe([$booked->id]);
    });
});

<?php

use App\Enums\Bookings\BookingStatus;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\WorkingHour;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingCreator;

beforeEach(function () {
    // A Monday
    $this->travelTo('2026-11-02 08:00:00');
    $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
});

/**
 * A service with someone working every weekday from nine to five
 */
function serviceWithRules(array $attributes = []): Service
{
    $service = Service::factory()->withRules()->create(['duration_minutes' => 60, ...$attributes]);
    $person = BookableResource::factory()->create();
    $person->services()->attach($service);

    foreach (range(1, 5) as $day) {
        WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => $day]);
    }

    return $service;
}

function ruleBooking(Service $service, string $date, string $time = '10:00', array $overrides = []): array
{
    return ['service_id' => $service->id, 'date' => $date, 'time' => $time, 'customer_name' => 'Morgan', 'customer_email' => 'morgan@example.test', ...$overrides];
}

test('a time inside the notice needed, or too far ahead, is refused and not offered', function () {
    $service = serviceWithRules();

    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-11-02'))
        ->assertSessionHasErrors(['time' => 'This needs 1 day notice.']);
    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-12-14'))
        ->assertSessionHasErrors(['time' => 'This can only be booked up to 30 days ahead.']);

    $this->getJson(route('admin.bookings.bookings.availability', ['service_id' => $service->id, 'date' => '2026-11-02']))
        ->assertJsonCount(0, 'slots');
    $this->getJson(route('admin.bookings.bookings.availability', ['service_id' => $service->id, 'date' => '2026-11-03']))
        ->assertJsonPath('slots.0.time', '09:00');

    expect(Booking::query()->count())->toBe(0);
});

test('staff can choose to ignore the rules, and the booking says so', function () {
    $service = serviceWithRules();

    $this->getJson(route('admin.bookings.bookings.availability', ['service_id' => $service->id, 'date' => '2026-11-02', 'ignore_rules' => 1]))
        ->assertJsonPath('slots.0.time', '09:00');

    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-11-02', overrides: ['ignore_rules' => true]))
        ->assertSessionHasNoErrors();

    expect(Booking::query()->sole()->rules_ignored)->toBeTrue();
});

test('a customer can only hold so many upcoming bookings of a service', function () {
    $service = serviceWithRules();

    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-11-04'))->assertSessionHasNoErrors();
    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-11-05'))
        ->assertSessionHasErrors(['customer_email' => 'This customer already has 1 upcoming, the most allowed at once.']);

    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-11-05', overrides: ['customer_email' => 'someone.else@example.test']))
        ->assertSessionHasNoErrors();

    Booking::query()->where('customer_email', 'morgan@example.test')->update(['status' => BookingStatus::Cancelled]);

    // Another time, as someone else now has 10:00
    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-11-05', '12:00'))->assertSessionHasNoErrors();
});

test('a walk-in needs no notice', function () {
    $this->travelTo('2026-11-02 10:00:00');
    $service = serviceWithRules();

    $this->post(route('admin.bookings.bookings.store'), ['service_id' => $service->id, 'walk_in' => true, 'customer_name' => 'Morgan'])
        ->assertSessionHasNoErrors();
});

test('the rules apply to timetable entries too', function () {
    $service = Service::factory()->groupClass()->withRules()->create();
    $soon = ServiceSession::factory()->for($service)->create(['starts_at' => '2026-11-02 18:00:00', 'ends_at' => '2026-11-02 19:00:00']);
    $later = ServiceSession::factory()->for($service)->create(['starts_at' => '2026-11-04 18:00:00', 'ends_at' => '2026-11-04 19:00:00']);

    $this->getJson(route('admin.bookings.bookings.availability', ['service_id' => $service->id]))
        ->assertJsonCount(1, 'sessions')
        ->assertJsonPath('sessions.0.id', $later->id);

    $this->post(route('admin.bookings.bookings.store'), ['service_id' => $service->id, 'session_id' => $soon->id, 'customer_name' => 'Morgan'])
        ->assertSessionHasErrors('session_id');
});

test('only a customer\'s own booking needs approval', function () {
    $service = serviceWithRules(['requires_approval' => true, 'min_notice_minutes' => null]);
    $creator = app(BookingCreator::class);

    $byStaff = $creator->create($service, null, ['customer_name' => 'Morgan', 'date' => '2026-11-03', 'time' => '10:00']);
    $byCustomer = $creator->create($service, null, ['customer_name' => 'Sam', 'date' => '2026-11-03', 'time' => '12:00'], byCustomer: true);

    expect($byStaff->status)->toBe(BookingStatus::Confirmed)
        ->and($byCustomer->status)->toBe(BookingStatus::Requested);
});

test('cancelling inside the window is recorded as late', function (string $now, bool $late) {
    $service = serviceWithRules(['min_notice_minutes' => null]);
    $this->post(route('admin.bookings.bookings.store'), ruleBooking($service, '2026-11-04'))->assertSessionHasNoErrors();
    $booking = Booking::query()->sole();

    $this->travelTo($now);
    $this->post(route('admin.bookings.bookings.status', $booking), ['status' => 'cancelled'])->assertSessionHasNoErrors();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($booking->is_late_cancellation)->toBe($late);
})->with([
    'more than a day before' => ['2026-11-03 09:59:00', false],
    'within the day before' => ['2026-11-03 10:01:00', true],
]);

test('the rules are saved on the service', function () {
    $service = Service::factory()->create();

    $this->put(route('admin.bookings.services.update', $service), [
        'name' => 'Haircut',
        'booking_type' => 'appointment',
        'price_type' => 'on_quote',
        'duration_minutes' => 30,
        'min_notice_minutes' => 60,
        'max_advance_days' => 14,
        'cancellation_hours' => 48,
        'max_per_customer' => 2,
        'requires_approval' => true,
    ])->assertSessionHasNoErrors();

    expect($service->fresh()->only(['min_notice_minutes', 'max_advance_days', 'cancellation_hours', 'max_per_customer', 'requires_approval']))
        ->toBe(['min_notice_minutes' => 60, 'max_advance_days' => 14, 'cancellation_hours' => 48, 'max_per_customer' => 2, 'requires_approval' => true]);
});

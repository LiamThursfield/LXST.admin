<?php

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\ResourceType;
use App\Enums\Bookings\WaitlistStatus;
use App\Enums\TenantModule;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Bookings\WorkingHour;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingAvailability;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A Monday
    $this->travelTo('2026-11-02 08:00:00');
    $this->actingAs($this->createUserWithPermissions(BookingsPermission::cases()));
});

describe('joining tables', function () {
    beforeEach(function () {
        $this->service = Service::factory()->table(1, 20)->create(['duration_minutes' => 90]);
        $this->start = CarbonImmutable::parse('2026-11-09 12:00');
    });

    function tableFor(Service $service, int $seats, ?string $group, array $attributes = []): BookableResource
    {
        $table = BookableResource::factory()->space($seats)->create(['combine_group' => $group, ...$attributes]);
        $table->services()->attach($service);

        return $table;
    }

    test('tables in the same group are joined when no one table fits, fewest first', function () {
        $first = tableFor($this->service, 4, 'Window');
        $second = tableFor($this->service, 4, 'Window');
        tableFor($this->service, 2, 'Window');
        tableFor($this->service, 6, 'Bar');

        $slot = app(BookingAvailability::class)->slotAt($this->service, null, $this->start, 8);

        expect(collect($slot->resources)->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id]);
    });

    test('a single table that fits is still used first', function () {
        tableFor($this->service, 4, 'Window');
        tableFor($this->service, 4, 'Window');
        $big = tableFor($this->service, 8, null);

        expect(app(BookingAvailability::class)->slotAt($this->service, null, $this->start, 8)->resources[0]->id)->toBe($big->id);
    });

    test('tables in different groups or locations, or busy ones, are not joined', function () {
        $here = OrganisationUnit::factory()->location()->create();
        $there = OrganisationUnit::factory()->location()->create();
        tableFor($this->service, 4, 'Window', ['location_id' => $here->id]);
        $busy = tableFor($this->service, 4, 'Window', ['location_id' => $here->id]);
        tableFor($this->service, 4, 'Window', ['location_id' => $there->id]);
        tableFor($this->service, 4, 'Bar', ['location_id' => $here->id]);
        Booking::factory()->create(['blocked_from' => '2026-11-09 11:00:00', 'blocked_until' => '2026-11-09 14:00:00'])->resources()->attach($busy);

        expect(app(BookingAvailability::class)->slotAt($this->service, $here, $this->start, 8))->toBeNull();
    });

    test('a booking for a big party takes every table joined', function () {
        tableFor($this->service, 4, 'Window');
        tableFor($this->service, 4, 'Window');

        $this->post(route('admin.bookings.bookings.store'), [
            'service_id' => $this->service->id,
            'date' => '2026-11-09',
            'time' => '12:00',
            'quantity' => 7,
            'customer_name' => 'Morgan',
        ])->assertSessionHasNoErrors();

        expect(Booking::query()->sole()->resources)->toHaveCount(2);
    });

    test('only a space can join others, and the group is saved', function () {
        $this->post(route('admin.bookings.resources.store'), ['type' => 'space', 'name' => 'Table 1', 'capacity' => 4, 'combine_group' => ' Window '])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.bookings.staff.store'), ['type' => 'person', 'name' => 'Sam', 'combine_group' => 'Window'])
            ->assertSessionHasNoErrors();

        expect(BookableResource::query()->where('type', ResourceType::Space)->sole()->combine_group)->toBe('Window')
            ->and(BookableResource::query()->where('type', ResourceType::Person)->sole()->combine_group)->toBeNull();
    });
});

describe('recurring bookings', function () {
    beforeEach(function () {
        $this->service = Service::factory()->create(['duration_minutes' => 60]);
        $this->person = BookableResource::factory()->create();
        $this->person->services()->attach($this->service);
        WorkingHour::factory()->for($this->person, 'resource')->create(['day_of_week' => 1]);
    });

    function repeatingData(Service $service, array $overrides = []): array
    {
        return ['service_id' => $service->id, 'date' => '2026-11-09', 'time' => '10:00', 'customer_name' => 'Morgan', 'repeat_weeks' => 1, 'repeat_times' => 3, ...$overrides];
    }

    test('it books the same time each week as a linked series', function () {
        $this->post(route('admin.bookings.bookings.store'), repeatingData($this->service))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '3 bookings created, repeating.');

        $bookings = Booking::query()->orderBy('starts_at')->get();

        expect($bookings->pluck('starts_at')->map->toDateString()->all())->toBe(['2026-11-09', '2026-11-16', '2026-11-23'])
            ->and($bookings->pluck('series_id')->unique())->toHaveCount(1)
            ->and($bookings[0]->series_id)->not->toBeNull();

        $this->get(route('admin.bookings.bookings.show', $bookings[1]))
            ->assertInertia(fn (Assert $page) => $page->has('series', 3)->where('series.1.current', true));
    });

    test('dates that don\'t fit are skipped and listed', function () {
        Booking::factory()->create(['blocked_from' => '2026-11-16 09:30:00', 'blocked_until' => '2026-11-16 10:30:00'])->resources()->attach($this->person);

        $this->post(route('admin.bookings.bookings.store'), repeatingData($this->service, ['repeat_weeks' => 1]))
            ->assertSessionHas('success', "2 of 3 bookings created. These dates didn't fit, so weren't booked: Mon 16 Nov.");

        expect(Booking::query()->whereNotNull('series_id')->count())->toBe(2);
    });

    test('if the first doesn\'t fit, nothing is booked', function () {
        Booking::factory()->create(['blocked_from' => '2026-11-09 09:30:00', 'blocked_until' => '2026-11-09 10:30:00'])->resources()->attach($this->person);

        $this->post(route('admin.bookings.bookings.store'), repeatingData($this->service))->assertSessionHasErrors('time');

        expect(Booking::query()->whereNotNull('series_id')->count())->toBe(0);
    });

    test('a walk-in can\'t repeat', function () {
        $this->travelTo('2026-11-09 10:00:00');

        $this->post(route('admin.bookings.bookings.store'), repeatingData($this->service, ['walk_in' => true]))->assertSessionHasNoErrors();

        expect(Booking::query()->sole()->series_id)->toBeNull();
    });

    test('timetable entries can repeat weekly, skipping busy weeks', function () {
        $class = Service::factory()->groupClass()->create();
        $coach = BookableResource::factory()->create();
        Booking::factory()->create(['blocked_from' => '2026-11-16 18:00:00', 'blocked_until' => '2026-11-16 19:00:00'])->resources()->attach($coach);

        $this->post(route('admin.bookings.sessions.store'), [
            'service_id' => $class->id,
            'date' => '2026-11-09',
            'time' => '18:00',
            'duration_minutes' => 60,
            'resource_ids' => [$coach->id],
            'repeat_times' => 3,
        ])->assertSessionHas('success', '2 of 3 added to the timetable. These weeks were busy, so were skipped: Mon 16 Nov.');

        expect(ServiceSession::query()->orderBy('starts_at')->pluck('starts_at')->map->toDateString()->all())->toBe(['2026-11-09', '2026-11-23']);
    });
});

describe('the waitlist', function () {
    test('it needs the view permission and the module', function () {
        $this->actingAs($this->createUserWithPermissions([]));
        $this->get(route('admin.bookings.waitlist.index'))->assertForbidden();

        $this->actingAs($this->createUserWithPermissions([BookingsPermission::ViewBookings]));
        $this->get(route('admin.bookings.waitlist.index'))->assertOk();
        $this->post(route('admin.bookings.waitlist.store'), [])->assertForbidden();

        tenant()->update(['modules' => [TenantModule::Crm]]);
        $this->get(route('admin.bookings.waitlist.index'))->assertNotFound();
    });

    test('someone can wait for a full timetable entry, or any time on a date, but not an enquiry', function () {
        $session = ServiceSession::factory()->create();
        $haircut = Service::factory()->create();

        $this->post(route('admin.bookings.waitlist.store'), ['service_id' => $session->service_id, 'session_id' => $session->id, 'customer_name' => 'Morgan', 'quantity' => 2])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.bookings.waitlist.store'), ['service_id' => $haircut->id, 'wanted_on' => '2026-11-10', 'customer_name' => 'Sam'])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.bookings.waitlist.store'), ['service_id' => $haircut->id, 'customer_name' => 'Sam'])
            ->assertSessionHasErrors('wanted_on');
        $this->post(route('admin.bookings.waitlist.store'), ['service_id' => Service::factory()->enquiry()->create()->id, 'wanted_on' => '2026-11-10', 'customer_name' => 'Jo'])
            ->assertSessionHasErrors('service_id');

        expect(WaitlistEntry::query()->where('status', WaitlistStatus::Waiting)->count())->toBe(2);
    });

    test('a place shows as free when a cancellation makes room, and booking it takes the place', function () {
        $service = Service::factory()->groupClass(2)->withRules()->create();
        $session = ServiceSession::factory()->for($service)->create(['starts_at' => '2026-11-02 18:00:00', 'ends_at' => '2026-11-02 19:00:00']);
        $taken = Booking::factory()->for($service)->create(['session_id' => $session->id, 'quantity' => 2]);
        $entry = WaitlistEntry::factory()->forSession($session)->create(['customer_name' => 'Morgan']);

        $this->get(route('admin.bookings.waitlist.index'))->assertInertia(fn (Assert $page) => $page->where('entries.data.0.place_free', false));

        $taken->update(['status' => BookingStatus::Cancelled]);

        $this->get(route('admin.bookings.waitlist.index'))->assertInertia(fn (Assert $page) => $page->where('entries.data.0.place_free', true));
        $this->get(route('admin.bookings.sessions.index'))->assertInertia(fn (Assert $page) => $page->where('sessions.data.0.waiting', 1));

        // The class is within its notice now, but they asked in time, so the place is still theirs
        $this->post(route('admin.bookings.waitlist.book', $entry))->assertRedirect();

        $entry->refresh();

        expect($entry->status)->toBe(WaitlistStatus::Booked)
            ->and($entry->booking->customer_name)->toBe('Morgan')
            ->and($entry->booking->session_id)->toBe($session->id);
    });

    test('booking someone waiting for a date opens the booking form filled in, and saving it books them', function () {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $person = BookableResource::factory()->create();
        $person->services()->attach($service);
        WorkingHour::factory()->for($person, 'resource')->create(['day_of_week' => 2]);
        $entry = WaitlistEntry::factory()->for($service)->create(['wanted_on' => '2026-11-10', 'customer_name' => 'Sam', 'customer_email' => 'sam@example.test']);

        $this->post(route('admin.bookings.waitlist.book', $entry))
            ->assertRedirect(route('admin.bookings.bookings.create', ['waitlist_entry_id' => $entry->id]));

        $this->get(route('admin.bookings.bookings.create', ['waitlist_entry_id' => $entry->id]))
            ->assertInertia(fn (Assert $page) => $page->where('prefill.date', '2026-11-10')->where('prefill.customer_name', 'Sam'));

        $this->post(route('admin.bookings.bookings.store'), [
            'service_id' => $service->id,
            'date' => '2026-11-10',
            'time' => '10:00',
            'customer_name' => 'Sam',
            'waitlist_entry_id' => $entry->id,
        ])->assertSessionHasNoErrors();

        expect($entry->fresh()->status)->toBe(WaitlistStatus::Booked)
            ->and($entry->fresh()->booking_id)->toBe(Booking::query()->sole()->id);
    });

    test('someone can be taken off, and can\'t then be booked from it', function () {
        $entry = WaitlistEntry::factory()->create();

        $this->delete(route('admin.bookings.waitlist.destroy', $entry))->assertRedirect();

        expect($entry->fresh()->status)->toBe(WaitlistStatus::Removed);

        $this->post(route('admin.bookings.waitlist.book', $entry))->assertNotFound();
    });
});

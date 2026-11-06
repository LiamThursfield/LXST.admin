<?php

namespace Database\Seeders;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\DepositType;
use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\PaymentRequirement;
use App\Enums\Bookings\ResourceType;
use App\Enums\Bookings\ServiceOptionType;
use App\Enums\Bookings\ServicePriceType;
use App\Enums\Bookings\TimeOffStatus;
use App\Enums\Bookings\TimeOffType;
use App\Enums\Bookings\WaitlistStatus;
use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\MessageLog;
use App\Models\Bookings\MessageTemplate;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceCategory;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\Role;
use App\Services\Bookings\BookingCreator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds example services: one of each booking type, each kind of price, with variations and add-ons,
 * so every part of the form has something to show. With the CRM, one is limited to a single location.
 * Also one resource of each type who can do them, with working hours, shifts this week, and time off
 * both waiting and approved. Then timetable entries for the class and event, and bookings of each
 * kind, made through BookingCreator, a weekly series, someone on the waitlist, and past ones including
 * a walk-in. Two tables can be joined for a bigger party. Dates are relative to now.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=BookingsSeeder`.
 * Safe to re-run: a category, service or resource that already exists is left alone.
 */
class BookingsSeeder extends Seeder
{
    public function run(): void
    {
        $sessions = ServiceCategory::query()->firstOrCreate(['name' => 'Sessions'], ['order' => 10]);
        $groups = ServiceCategory::query()->firstOrCreate(['name' => 'Groups and events'], ['order' => 20]);
        $spaces = ServiceCategory::query()->firstOrCreate(['name' => 'Spaces and visits'], ['order' => 30]);

        $consultation = $this->seedService('consultation', [
            'name' => 'Consultation',
            'description' => 'A one-to-one session, with time to reset afterwards.',
            'category_id' => $sessions->id,
            'booking_type' => BookingType::Appointment,
            'price_type' => ServicePriceType::Fixed,
            'price' => 3500,
            'duration_minutes' => 45,
            'buffer_after_minutes' => 15,
            'min_notice_minutes' => 120,
            'cancellation_hours' => 24,
            'payment_requirement' => PaymentRequirement::Deposit,
            'deposit_type' => DepositType::Percent,
            'deposit_amount' => 20,
        ]);

        if ($consultation->wasRecentlyCreated) {
            $consultation->options()->createMany([
                ['type' => ServiceOptionType::Variation, 'name' => 'Short', 'price_adjustment' => -1000, 'duration_adjustment' => -15, 'order' => 0],
                ['type' => ServiceOptionType::Variation, 'name' => 'Deluxe', 'price_adjustment' => 2000, 'duration_adjustment' => 30, 'order' => 1],
                ['type' => ServiceOptionType::AddOn, 'name' => 'Take-home notes', 'price_adjustment' => 500, 'duration_adjustment' => 0, 'order' => 2],
            ]);
        }

        $this->seedService('group-session', [
            'name' => 'Group session',
            'description' => 'A scheduled session for up to 20 people.',
            'category_id' => $groups->id,
            'booking_type' => BookingType::GroupClass,
            'price_type' => ServicePriceType::Fixed,
            'price' => 1200,
            'duration_minutes' => 60,
            'capacity' => 20,
        ]);

        $this->seedService('table-booking', [
            'name' => 'Table booking',
            'description' => 'Space for a party of up to 8.',
            'category_id' => $groups->id,
            'booking_type' => BookingType::Table,
            'price_type' => ServicePriceType::OnQuote,
            'duration_minutes' => 90,
            'buffer_after_minutes' => 15,
            'min_party_size' => 1,
            'max_party_size' => 8,
        ]);

        $this->seedService('open-evening', [
            'name' => 'Open evening',
            'description' => 'A ticketed evening event.',
            'category_id' => $groups->id,
            'booking_type' => BookingType::Ticket,
            'price_type' => ServicePriceType::Fixed,
            'price' => 1500,
            'duration_minutes' => 180,
            'capacity' => 100,
        ]);

        $roomHire = $this->seedService('room-hire', [
            'name' => 'Room hire',
            'description' => 'Hire the room by the hour, with time to clear up afterwards.',
            'category_id' => $spaces->id,
            'booking_type' => BookingType::Hire,
            'price_type' => ServicePriceType::Fixed,
            'price' => 4000,
            'buffer_after_minutes' => 30,
            'hire_unit' => HireUnit::Hour,
            'min_hire_units' => 2,
            'max_hire_units' => 8,
        ]);

        if ($roomHire->wasRecentlyCreated && tenant()?->hasModule(TenantModule::Crm)) {
            $location = OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->orderBy('id')->first();

            if ($location !== null) {
                $roomHire->locations()->sync([$location->id]);
            }
        }

        $this->seedService('home-visit', [
            'name' => 'Home visit',
            'description' => 'We come to you. The price depends on the job.',
            'category_id' => $spaces->id,
            'booking_type' => BookingType::Visit,
            'price_type' => ServicePriceType::From,
            'price' => 6000,
            'duration_minutes' => 60,
            'travel_minutes' => 30,
        ]);

        $this->seedService('large-project', [
            'name' => 'Large project',
            'description' => 'Tell us what you need and we\'ll send a quote.',
            'booking_type' => BookingType::Enquiry,
            'price_type' => ServicePriceType::OnQuote,
            'is_bookable_online' => false,
            'requires_approval' => true,
            'max_per_customer' => 1,
        ]);

        $this->seedResources();
        $this->seedSessions();
        $this->seedBookings();
        $this->seedMessages();
        $this->seedPayments();
    }

    /**
     * A deposit paid online, money taken in person, and a payment link waiting. Made-up Stripe IDs,
     * as nothing here goes to Stripe.
     */
    protected function seedPayments(): void
    {
        if (Payment::query()->exists()) {
            return;
        }

        $priced = Booking::query()->whereNotNull('price')->whereNotNull('starts_at')->orderBy('id')->limit(3)->get();

        if ($priced->count() < 3) {
            return;
        }

        Payment::factory()->for($priced[0])->create(['amount' => (int) round((int) $priced[0]->price * 0.2), 'stripe_checkout_session_id' => 'cs_test_seeded_deposit']);
        Payment::factory()->for($priced[1])->inPerson()->create(['amount' => (int) $priced[1]->price, 'note' => 'Paid at the desk']);
        Payment::factory()->for($priced[2])->pending()->create(['amount' => (int) $priced[2]->price, 'stripe_checkout_session_id' => 'cs_test_seeded_link']);
    }

    /**
     * The tenant's own wording for one message, and a few sent, including a failure to look into
     */
    protected function seedMessages(): void
    {
        MessageTemplate::query()->firstOrCreate(['event' => MessageEvent::Booked->value], [
            'sms_body' => '{business}: see you {date} at {time} for {service}. Reply STOP to opt out.',
        ]);

        // Follow-ups are off by default; this one's on, a few hours after the booking
        MessageTemplate::query()->firstOrCreate(['event' => MessageEvent::FollowUp->value], [
            'is_enabled' => true,
            'offset_hours' => 3,
        ]);

        if (MessageLog::query()->exists()) {
            return;
        }

        foreach (Booking::query()->whereNotNull('customer_email')->whereNotNull('starts_at')->orderBy('id')->limit(3)->get() as $booking) {
            MessageLog::factory()->for($booking)->create(['recipient' => $booking->customer_email]);
        }

        $failed = Booking::query()->whereNotNull('starts_at')->orderByDesc('id')->first();

        if ($failed !== null) {
            MessageLog::factory()->for($failed)->failed()->create();
            MessageLog::factory()->for($failed)->custom()->create(['recipient' => $failed->customer_email]);
        }
    }

    protected function seedSessions(): void
    {
        if (ServiceSession::query()->exists()) {
            return;
        }

        $room = BookableResource::query()->where('slug', 'main-room')->value('id');
        // Wednesday of next week: far enough off to miss the seeded time off
        $wednesday = now()->startOfWeek()->addWeek()->addDays(2)->startOfDay();

        foreach ([['group-session', 18, 60], ['open-evening', 19, 180]] as [$slug, $hour, $minutes]) {
            $service = Service::query()->where('slug', $slug)->firstOrFail();
            $start = $slug === 'open-evening' ? $wednesday->copy()->addDay()->setTime($hour, 0) : $wednesday->copy()->setTime($hour, 0);

            $session = ServiceSession::query()->create([
                'service_id' => $service->id,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes($minutes),
            ]);
            $session->resources()->sync($room === null ? [] : [$room]);
        }
    }

    protected function seedBookings(): void
    {
        if (Booking::query()->exists()) {
            return;
        }

        $creator = app(BookingCreator::class);
        $date = now()->startOfWeek()->addWeek()->addDays(2)->toDateString();
        $service = fn (string $slug) => Service::query()->where('slug', $slug)->firstOrFail();
        $customer = fn (string $name) => ['customer_name' => $name, 'customer_email' => Str::slug($name, '.').'@example.test'];

        $consultation = $service('consultation');
        $creator->create($consultation, null, [...$customer('Morgan Reed'), 'date' => $date, 'time' => '10:00', 'option_ids' => [$consultation->options()->where('name', 'Deluxe')->value('id')]]);
        $creator->create($service('home-visit'), null, [...$customer('Priya Shah'), 'date' => $date, 'time' => '14:00']);
        $creator->create($service('table-booking'), null, [...$customer('Tom Baker'), 'date' => $date, 'time' => '12:00', 'quantity' => 4]);
        $creator->create($service('room-hire'), null, [...$customer('Lee Carter'), 'date' => $date, 'time' => '14:00', 'quantity' => 2]);

        $session = ServiceSession::query()->where('service_id', $service('group-session')->id)->firstOrFail();
        $creator->create($service('group-session'), null, [...$customer('Ana Lopez'), 'session_id' => $session->id, 'quantity' => 2]);

        $creator->create($service('large-project'), null, [...$customer('Sasha Green'), 'notes' => 'Looking for a quote for the spring.']);

        $creator->cancel($creator->create($consultation, null, [...$customer('Jo Marsh'), 'date' => $date, 'time' => '15:00']));

        // Weekly, starting the week after, with whoever is free
        $creator->createSeries($consultation, null, [...$customer('Robin Hale'), 'date' => CarbonImmutable::parse($date)->addWeek()->toDateString(), 'time' => '16:00'], everyWeeks: 1, times: 3);

        // Someone waiting for the group session, should a place come up
        WaitlistEntry::query()->create([
            'service_id' => $session->service_id,
            'session_id' => $session->id,
            ...$customer('Ellis Ward'),
            'quantity' => 1,
            'status' => WaitlistStatus::Waiting,
        ]);

        // Past bookings can't be made through the creator, which only takes times to come
        Booking::factory()->past()->create([
            'service_id' => $consultation->id,
            'service_name' => $consultation->name,
            ...$customer('Dee Walsh'),
        ]);
        Booking::factory()->past()->walkIn()->create([
            'service_id' => $consultation->id,
            'service_name' => $consultation->name,
            'status' => BookingStatus::Completed,
            ...$customer('Kit Moss'),
        ]);
    }

    protected function seedResources(): void
    {
        $sam = $this->seedResource('sam-taylor', [
            'type' => ResourceType::Person,
            'name' => 'Sam Taylor',
            'job_title' => 'Senior team member',
            'description' => 'Fifteen years of experience.',
            // The editor, so asking for time off can be tried by signing in as them
            'user_id' => RoleUserSeeder::userFor(Role::Editor)?->id,
            'is_public' => true,
        ], weekdays: [['09:00', '17:00']]);

        $alex = $this->seedResource('alex-morgan', [
            'type' => ResourceType::Person,
            'name' => 'Alex Morgan',
            'job_title' => 'Team member',
            'user_id' => RoleUserSeeder::userFor(Role::Viewer)?->id,
        ], weekdays: [['09:00', '13:00'], ['14:00', '18:00']]);

        $room = $this->seedResource('main-room', [
            'type' => ResourceType::Space,
            'name' => 'Main room',
            'capacity' => 20,
        ], weekdays: [['08:00', '22:00']]);

        $this->seedResource('projector', [
            'type' => ResourceType::Equipment,
            'name' => 'Projector',
        ]);

        // Two tables that can be joined for a bigger party
        $tables = array_map(fn (int $number) => $this->seedResource("table-{$number}", [
            'type' => ResourceType::Space,
            'name' => "Table {$number}",
            'capacity' => 4,
            'combine_group' => 'Window',
        ]), [1, 2]);

        $van = $this->seedResource('van', [
            'type' => ResourceType::Vehicle,
            'name' => 'Van',
        ], weekdays: [['08:00', '18:00']]);

        if ($sam->wasRecentlyCreated) {
            // A senior person charges more, and takes less time
            $sam->services()->sync([
                Service::query()->where('slug', 'consultation')->value('id') => ['price' => 5000, 'duration_minutes' => 30],
            ] + $this->serviceIds(['group-session']));
            $sam->shifts()->create(['works_on' => now()->startOfWeek()->addDays(5)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '14:00', 'note' => 'Weekend cover']);
            $sam->timeOff()->create(['type' => TimeOffType::Holiday, 'status' => TimeOffStatus::Requested, 'starts_on' => now()->addWeeks(2)->toDateString(), 'ends_on' => now()->addWeeks(2)->addDays(4)->toDateString(), 'requested_by_id' => $sam->user_id]);
        }

        if ($alex->wasRecentlyCreated) {
            $alex->services()->sync($this->serviceIds(['consultation', 'home-visit', 'large-project']));
            $alex->timeOff()->create(['type' => TimeOffType::Holiday, 'status' => TimeOffStatus::Approved, 'starts_on' => now()->addDay()->toDateString(), 'ends_on' => now()->addDays(2)->toDateString(), 'reviewed_at' => now()]);
        }

        if ($room->wasRecentlyCreated) {
            $room->services()->sync($this->serviceIds(['group-session', 'room-hire', 'open-evening', 'table-booking']));
        }

        foreach ($tables as $table) {
            if ($table->wasRecentlyCreated) {
                $table->services()->sync($this->serviceIds(['table-booking']));
            }
        }

        if ($van->wasRecentlyCreated) {
            $van->services()->sync($this->serviceIds(['home-visit']));
            $van->timeOff()->create(['type' => TimeOffType::Maintenance, 'status' => TimeOffStatus::Approved, 'starts_on' => now()->addWeek()->toDateString(), 'ends_on' => now()->addWeek()->toDateString(), 'reason' => 'Annual service', 'reviewed_at' => now()]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: string, 1: string}>  $weekdays  The periods it works, Monday to Friday
     */
    protected function seedResource(string $slug, array $attributes, array $weekdays = []): BookableResource
    {
        $resource = BookableResource::withTrashed()->firstOrCreate(['slug' => $slug], $attributes);

        if ($resource->wasRecentlyCreated) {
            foreach (range(1, 5) as $day) {
                foreach ($weekdays as [$startsAt, $endsAt]) {
                    $resource->workingHours()->create(['day_of_week' => $day, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
                }
            }
        }

        return $resource;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<int, array<string, mixed>>
     */
    protected function serviceIds(array $slugs): array
    {
        return Service::query()->whereIn('slug', $slugs)->pluck('id')->mapWithKeys(fn (int $id) => [$id => []])->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function seedService(string $slug, array $attributes): Service
    {
        return Service::withTrashed()->firstOrCreate(['slug' => $slug], $attributes);
    }
}

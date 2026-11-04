<?php

namespace Database\Seeders;

use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\ServiceOptionType;
use App\Enums\Bookings\ServicePriceType;
use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceCategory;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Seeder;

/**
 * Seeds example services: one of each booking type, each kind of price, with variations and add-ons,
 * so every part of the form has something to show. With the CRM, one is limited to a single location.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=BookingsSeeder`.
 * Safe to re-run: a category or service that already exists is left alone.
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
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function seedService(string $slug, array $attributes): Service
    {
        return Service::withTrashed()->firstOrCreate(['slug' => $slug], $attributes);
    }
}

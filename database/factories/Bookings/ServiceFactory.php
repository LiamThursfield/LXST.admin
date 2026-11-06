<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\DepositType;
use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\ResourceType;
use App\Enums\Bookings\ServicePriceType;
use App\Enums\BookingType;
use App\Models\Bookings\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Define the model's default state (a fixed price, one hour appointment).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'booking_type' => BookingType::Appointment,
            'price_type' => ServicePriceType::Fixed,
            'price' => 2500,
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'is_bookable_online' => true,
        ];
    }

    public function groupClass(int $capacity = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_type' => BookingType::GroupClass,
            'capacity' => $capacity,
        ]);
    }

    public function table(int $minPartySize = 1, int $maxPartySize = 8): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_type' => BookingType::Table,
            'price_type' => ServicePriceType::OnQuote,
            'price' => null,
            'min_party_size' => $minPartySize,
            'max_party_size' => $maxPartySize,
        ]);
    }

    public function hire(HireUnit $unit = HireUnit::Hour): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_type' => BookingType::Hire,
            'duration_minutes' => null,
            'hire_unit' => $unit,
            'min_hire_units' => 1,
            'max_hire_units' => null,
        ]);
    }

    public function visit(int $travelMinutes = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_type' => BookingType::Visit,
            'travel_minutes' => $travelMinutes,
        ]);
    }

    public function ticket(int $capacity = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_type' => BookingType::Ticket,
            'capacity' => $capacity,
        ]);
    }

    public function enquiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_type' => BookingType::Enquiry,
            'price_type' => ServicePriceType::OnQuote,
            'price' => null,
            'duration_minutes' => null,
        ]);
    }

    /**
     * Each booking takes one of each of these, e.g. a person and a space
     *
     * @param  list<ResourceType>  $types
     */
    public function needing(array $types): static
    {
        return $this->state(fn (array $attributes) => [
            'required_resource_types' => array_map(fn (ResourceType $type) => $type->value, $types),
        ]);
    }

    public function startingFrom(int $price = 2500): static
    {
        return $this->state(fn (array $attributes) => [
            'price_type' => ServicePriceType::From,
            'price' => $price,
        ]);
    }

    /**
     * Needs a day's notice, books up to a month ahead, counts cancelling within a day of the
     * start as late, and allows one upcoming booking per customer
     */
    public function withRules(): static
    {
        return $this->state(fn (array $attributes) => [
            'min_notice_minutes' => 1440,
            'max_advance_days' => 30,
            'cancellation_hours' => 24,
            'max_per_customer' => 1,
        ]);
    }

    /**
     * Charges half the price for a no-show and a set £10 for a late cancellation
     */
    public function withFees(): static
    {
        return $this->state(fn (array $attributes) => [
            'no_show_fee_type' => DepositType::Percent,
            'no_show_fee_amount' => 50,
            'late_cancellation_fee_type' => DepositType::Fixed,
            'late_cancellation_fee_amount' => 1000,
        ]);
    }

    public function needingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_approval' => true,
        ]);
    }

    public function notBookableOnline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bookable_online' => false,
        ]);
    }
}

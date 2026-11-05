<?php

namespace Database\Factories\Bookings;

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

    public function notBookableOnline(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bookable_online' => false,
        ]);
    }
}

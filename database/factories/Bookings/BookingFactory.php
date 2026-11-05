<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\ServicePriceType;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * Define the model's default state (a confirmed hour next week). Real bookings go through
     * BookingCreator, which also checks the time and chooses resources.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addWeek()->startOfHour();

        return [
            'service_id' => Service::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'status' => BookingStatus::Confirmed,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'blocked_from' => $start,
            'blocked_until' => $start->copy()->addHour(),
            'timezone' => (string) config('app.timezone'),
            'quantity' => 1,
            'service_name' => ucfirst(fake()->words(2, true)),
            'price_type' => ServicePriceType::Fixed,
            'price' => 2500,
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * An enquiry with no time yet
     */
    public function enquiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Requested,
            'starts_at' => null,
            'ends_at' => null,
            'blocked_from' => null,
            'blocked_until' => null,
            'price_type' => ServicePriceType::OnQuote,
            'price' => null,
        ]);
    }

    /**
     * Booked in person to start straight away, and checked in
     */
    public function walkIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_walk_in' => true,
            'status' => BookingStatus::CheckedIn,
        ]);
    }

    public function past(): static
    {
        $start = now()->subWeek()->startOfHour();

        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'blocked_from' => $start,
            'blocked_until' => $start->copy()->addHour(),
        ]);
    }
}

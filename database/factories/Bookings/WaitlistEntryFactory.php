<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\WaitlistStatus;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    /**
     * Define the model's default state (waiting for any time next week).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'wanted_on' => now()->addWeek()->toDateString(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'quantity' => 1,
            'status' => WaitlistStatus::Waiting,
        ];
    }

    /**
     * Waiting for a place on a timetable entry
     */
    public function forSession(ServiceSession $session): static
    {
        return $this->state(fn (array $attributes) => [
            'service_id' => $session->service_id,
            'session_id' => $session->id,
            'wanted_on' => null,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WaitlistStatus::Removed,
        ]);
    }
}

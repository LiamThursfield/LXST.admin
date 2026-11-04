<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Enums\Bookings\TimeOffType;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    protected $model = TimeOff::class;

    /**
     * Define the model's default state (a holiday request for next week).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_id' => BookableResource::factory(),
            'type' => TimeOffType::Holiday,
            'status' => TimeOffStatus::Requested,
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TimeOffStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TimeOffStatus::Declined,
            'reviewed_at' => now(),
        ]);
    }

    public function sickness(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TimeOffType::Sickness,
            'reason' => 'Unwell',
        ]);
    }
}

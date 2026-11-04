<?php

namespace Database\Factories\Bookings;

use App\Models\Bookings\BookableResource;
use App\Models\Bookings\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    protected $model = WorkingHour::class;

    /**
     * Define the model's default state (Monday, nine to five).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_id' => BookableResource::factory(),
            'day_of_week' => 1,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ];
    }

    public function overnight(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => '18:00',
            'ends_at' => '02:00',
        ]);
    }
}

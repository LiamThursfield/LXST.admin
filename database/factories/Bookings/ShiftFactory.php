<?php

namespace Database\Factories\Bookings;

use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * Define the model's default state (tomorrow, ten to four).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_id' => BookableResource::factory(),
            'works_on' => now()->addDay()->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '16:00',
        ];
    }
}

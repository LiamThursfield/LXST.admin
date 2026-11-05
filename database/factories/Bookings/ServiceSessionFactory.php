<?php

namespace Database\Factories\Bookings;

use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSession>
 */
class ServiceSessionFactory extends Factory
{
    protected $model = ServiceSession::class;

    /**
     * Define the model's default state (an hour long class next week).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addWeek()->startOfHour();

        return [
            'service_id' => Service::factory()->groupClass(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
        ];
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }

    public function past(): static
    {
        $start = now()->subWeek()->startOfHour();

        return $this->state(fn (array $attributes) => [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
        ]);
    }
}

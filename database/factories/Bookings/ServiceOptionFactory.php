<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\ServiceOptionType;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOption>
 */
class ServiceOptionFactory extends Factory
{
    protected $model = ServiceOption::class;

    /**
     * Define the model's default state (a variation that costs more and takes longer).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'type' => ServiceOptionType::Variation,
            'name' => ucfirst(fake()->words(2, true)),
            'price_adjustment' => 500,
            'duration_adjustment' => 15,
            'order' => 0,
        ];
    }

    public function addOn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServiceOptionType::AddOn,
        ]);
    }
}

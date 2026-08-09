<?php

namespace Database\Factories\Tracker;

use App\Models\Tracker\Holiday;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-1 month', '+1 month');
        $endsOn = (clone $startsOn)->modify('+'.fake()->numberBetween(1, 14).' days');

        return [
            'user_id' => User::factory(),
            'label' => fake()->optional()->words(2, true),
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => $endsOn->format('Y-m-d'),
        ];
    }

    /**
     * A holiday owned by the given user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}

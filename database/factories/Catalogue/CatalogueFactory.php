<?php

namespace Database\Factories\Catalogue;

use App\Models\Catalogue\Catalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Catalogue>
 */
class CatalogueFactory extends Factory
{
    protected $model = Catalogue::class;

    /**
     * Define the model's default state (published, and applying all the time).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->optional()->sentence(),
            'is_published' => true,
            'available_days' => null,
            'available_from' => null,
            'available_until' => null,
            'starts_on' => null,
            'ends_on' => null,
            'order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    /**
     * Applies only on the given days and between the given times, e.g. a weekday lunch menu
     *
     * @param  list<int>|null  $days  ISO days of the week, 1 being Monday
     */
    public function availableAt(?array $days, ?string $from = null, ?string $until = null): static
    {
        return $this->state(fn (array $attributes) => [
            'available_days' => $days,
            'available_from' => $from,
            'available_until' => $until,
        ]);
    }

    /**
     * Applies only between two dates, e.g. a seasonal menu. Defaults to the coming month.
     */
    public function seasonal(?string $startsOn = null, ?string $endsOn = null): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => $startsOn ?? now()->addWeek()->toDateString(),
            'ends_on' => $endsOn ?? now()->addWeeks(5)->toDateString(),
        ]);
    }
}

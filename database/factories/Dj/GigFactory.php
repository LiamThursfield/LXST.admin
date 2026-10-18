<?php

namespace Database\Factories\Dj;

use App\Models\Dj\Gig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gig>
 */
class GigFactory extends Factory
{
    protected $model = Gig::class;

    /**
     * Define the model's default state (a four-hour gig next week, with no overrides).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addWeek()->setTime(21, 0);

        return [
            'name' => ucwords(fake()->words(2, true)),
            'venue' => fake()->company(),
            'location' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(4),
            'timezone' => 'Europe/London',
            'notes' => null,
            'is_pinned' => false,
            'request_settings' => null,
        ];
    }

    /**
     * On between the given times
     */
    public function between(CarbonImmutable $startsAt, CarbonImmutable $endsAt): static
    {
        return $this->state(fn () => ['starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}

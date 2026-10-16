<?php

namespace Database\Factories\Cms;

use App\Models\Cms\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    /**
     * Define the model's default state (an enabled, temporary redirect with no dates).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url_from' => '/'.fake()->unique()->slug(3),
            'url_to' => '/'.fake()->unique()->slug(3),
            'is_enabled' => true,
            'is_permanent' => false,
            'published_at' => null,
            'expired_at' => null,
        ];
    }

    public function permanent(): static
    {
        return $this->state(['is_permanent' => true]);
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

    public function scheduled(): static
    {
        return $this->state(['published_at' => now()->addWeek()]);
    }

    public function expired(): static
    {
        return $this->state(['expired_at' => now()->subDay()]);
    }
}

<?php

namespace Database\Factories\Dj;

use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestPass>
 */
class GuestPassFactory extends Factory
{
    protected $model = GuestPass::class;

    /**
     * Define the model's default state (an active pass that can add songs).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'song_list_id' => SongList::factory(),
            'label' => fake()->randomElement(['Bride', 'Groom', 'Planner', 'Family']),
            'password' => fake()->word().'-'.fake()->word().'-'.fake()->numberBetween(10, 99),
            'can_add' => true,
            'expires_at' => null,
            'revoked_at' => null,
            'last_used_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function viewOnly(): static
    {
        return $this->state(fn () => ['can_add' => false]);
    }
}

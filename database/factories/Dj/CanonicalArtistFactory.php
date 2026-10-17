<?php

namespace Database\Factories\Dj;

use App\Models\Dj\CanonicalArtist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanonicalArtist>
 */
class CanonicalArtistFactory extends Factory
{
    protected $model = CanonicalArtist::class;

    /**
     * Define the model's default state (an unverified artist).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }
}

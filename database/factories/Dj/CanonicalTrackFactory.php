<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanonicalTrack>
 */
class CanonicalTrackFactory extends Factory
{
    protected $model = CanonicalTrack::class;

    /**
     * Define the model's default state (an unverified track by a new artist).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'canonical_artist_id' => CanonicalArtist::factory(),
            'title' => ucwords(fake()->words(3, true)),
            'bpm' => fake()->randomFloat(2, 70, 180),
            'duration' => fake()->numberBetween(120, 480),
            'key' => fake()->randomElement(MusicalKey::cases()),
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }
}

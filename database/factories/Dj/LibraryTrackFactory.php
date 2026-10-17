<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryTrack>
 */
class LibraryTrackFactory extends Factory
{
    protected $model = LibraryTrack::class;

    /**
     * Define the model's default state (a track with no artist, not yet matched to a canonical track).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'library_artist_id' => null,
            'canonical_track_id' => null,
            'source_track_id' => (string) fake()->unique()->numberBetween(1, 999_999),
            'title' => ucwords(fake()->words(3, true)),
            'bpm' => fake()->randomFloat(2, 70, 180),
            'duration' => fake()->numberBetween(120, 480),
            'key' => fake()->randomElement(MusicalKey::cases()),
        ];
    }
}

<?php

namespace Database\Factories\Dj;

use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryArtist>
 */
class LibraryArtistFactory extends Factory
{
    protected $model = LibraryArtist::class;

    /**
     * Define the model's default state (an artist not yet matched to a canonical artist).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'canonical_artist_id' => null,
            'source_artist_id' => (string) fake()->unique()->numberBetween(1, 999_999),
            'name' => fake()->name(),
        ];
    }
}

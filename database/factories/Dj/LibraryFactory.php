<?php

namespace Database\Factories\Dj;

use App\Enums\Dj\LibrarySource;
use App\Models\Dj\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Library>
 */
class LibraryFactory extends Factory
{
    protected $model = Library::class;

    /**
     * Define the model's default state (a Rekordbox XML library).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'source' => LibrarySource::RekordboxXml,
        ];
    }

    public function source(LibrarySource $source): static
    {
        return $this->state(fn () => ['source' => $source]);
    }
}

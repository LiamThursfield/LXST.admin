<?php

namespace Database\Factories\Dj;

use App\Models\Dj\Library;
use App\Models\Dj\LibraryTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryTag>
 */
class LibraryTagFactory extends Factory
{
    protected $model = LibraryTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'source_tag_id' => (string) fake()->unique()->numberBetween(1, 999_999),
            'name' => ucfirst(fake()->word()),
            'category' => 'Genre',
        ];
    }
}

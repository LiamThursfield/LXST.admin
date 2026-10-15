<?php

namespace Database\Factories\FileManager;

use App\Models\FileManager\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    /**
     * Define the model's default state (a top-level folder).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::slug(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'name' => $name,
            // Follows the final name, including any override
            'path' => fn (array $attributes) => $attributes['name'],
        ];
    }

    /**
     * A folder inside the given folder
     */
    public function in(Folder $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'path' => fn (array $attributes) => $parent->path.'/'.$attributes['name'],
        ]);
    }
}

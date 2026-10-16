<?php

namespace Database\Factories\FileManager;

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * Define the model's default state (a top-level JPEG image).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::slug(fake()->unique()->words(2, true)).'.jpg';

        return [
            'folder_id' => null,
            'name' => $name,
            // Follows the final name, including any override
            'path' => fn (array $attributes) => $attributes['name'],
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'alt_text' => null,
        ];
    }

    /**
     * A file inside the given folder
     */
    public function in(Folder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'folder_id' => $folder->id,
            'path' => fn (array $attributes) => $folder->path.'/'.$attributes['name'],
        ]);
    }
}

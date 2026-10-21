<?php

namespace Database\Factories\Cms;

use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Metadata>
 */
class MetadataFactory extends Factory
{
    protected $model = Metadata::class;

    /**
     * Define the model's default state (a page's SEO and social metadata).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst(fake()->words(3, true));
        $description = fake()->sentence(12);

        return [
            'metadatable_type' => (new Page)->getMorphClass(),
            'metadatable_id' => Page::factory(),
            'title' => $title,
            'description' => $description,
            'keywords' => implode(', ', fake()->words(4)),
            'canonical' => null,
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
            'og_url' => null,
            'og_image_file_id' => null,
        ];
    }
}

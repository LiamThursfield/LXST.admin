<?php

namespace Database\Factories\Cms;

use App\Enums\Cms\TemplateType;
use App\Models\Cms\Layout;
use App\Models\Cms\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Layout>
 */
class LayoutFactory extends Factory
{
    protected $model = Layout::class;

    /**
     * Define the model's default state (a layout using a new layout template with no fields).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'template_id' => Template::factory()->ofType(TemplateType::Layout),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
        ];
    }
}

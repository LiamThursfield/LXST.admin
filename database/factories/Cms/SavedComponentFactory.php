<?php

namespace Database\Factories\Cms;

use App\Enums\Cms\TemplateType;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SavedComponent>
 */
class SavedComponentFactory extends Factory
{
    protected $model = SavedComponent::class;

    /**
     * Define the model's default state (a saved component using a new component template with no fields).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'template_id' => Template::factory()->ofType(TemplateType::Component),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
        ];
    }
}

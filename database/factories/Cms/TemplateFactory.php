<?php

namespace Database\Factories\Cms;

use App\Enums\Cms\TemplateType;
use App\Models\Cms\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    /**
     * Define the model's default state (a page template with no fields).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'type' => TemplateType::Page,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => null,
        ];
    }

    public function ofType(TemplateType $type): static
    {
        return $this->state(['type' => $type]);
    }
}

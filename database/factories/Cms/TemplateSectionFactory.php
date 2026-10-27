<?php

namespace Database\Factories\Cms;

use App\Models\Cms\Template;
use App\Models\Cms\TemplateSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateSection>
 */
class TemplateSectionFactory extends Factory
{
    protected $model = TemplateSection::class;

    /**
     * Define the model's default state (an open section of a page template).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => null,
            'is_collapsed' => false,
            'order' => 0,
        ];
    }

    /**
     * A section that starts collapsed in the content editor
     */
    public function collapsed(): static
    {
        return $this->state(['is_collapsed' => true]);
    }
}

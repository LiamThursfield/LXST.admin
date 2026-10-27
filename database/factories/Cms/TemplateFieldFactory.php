<?php

namespace Database\Factories\Cms;

use App\Data\Cms\TemplateFieldSettings;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateFieldWidth;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\TemplateSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemplateField>
 */
class TemplateFieldFactory extends Factory
{
    protected $model = TemplateField::class;

    /**
     * Define the model's default state (an optional text field).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'template_id' => Template::factory(),
            'type' => TemplateFieldType::Text,
            'name' => ucfirst($name),
            'slug' => Str::slug($name, '_'),
            'description' => null,
            'is_required' => false,
            'order' => 0,
            'settings' => null,
        ];
    }

    /**
     * A field of the given type, named after it
     */
    public function ofType(TemplateFieldType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'name' => $type->label(),
            'slug' => Str::slug($type->value, '_'),
        ]);
    }

    /**
     * A component or repeater field that uses the given template
     */
    public function usingTemplate(Template $template): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $template->type->value === 'repeater' ? TemplateFieldType::Repeater : TemplateFieldType::Component,
            'settings' => ['template_id' => $template->id],
        ]);
    }

    /**
     * A field in the given section, of the section's template
     */
    public function inSection(TemplateSection $section): static
    {
        return $this->state([
            'template_id' => $section->template_id,
            'section_id' => $section->id,
        ]);
    }

    /**
     * A field that takes half the content editor's width, keeping any other settings
     */
    public function halfWidth(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => TemplateFieldSettings::from([
                ...match (true) {
                    $attributes['settings'] instanceof TemplateFieldSettings => $attributes['settings']->toArray(),
                    is_array($attributes['settings']) => $attributes['settings'],
                    default => [],
                },
                'width' => TemplateFieldWidth::Half,
            ]),
        ]);
    }
}

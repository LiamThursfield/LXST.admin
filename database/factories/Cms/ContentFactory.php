<?php

namespace Database\Factories\Cms;

use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Content;
use App\Models\Cms\Page;
use App\Models\Cms\TemplateField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    protected $model = Content::class;

    /**
     * Define the model's default state (a page's text value for one of its template's fields).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contentable_type' => (new Page)->getMorphClass(),
            'contentable_id' => Page::factory(),
            'template_field_id' => TemplateField::factory(),
            'template_field_type' => TemplateFieldType::Text,
            'data' => fake()->sentence(),
        ];
    }

    /**
     * The value of the given field, typed to match it
     */
    public function forField(TemplateField $field, mixed $data): static
    {
        return $this->state(fn () => [
            'template_field_id' => $field->id,
            'template_field_type' => $field->type,
            'data' => $data,
        ]);
    }
}

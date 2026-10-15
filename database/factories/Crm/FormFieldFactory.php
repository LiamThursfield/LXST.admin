<?php

namespace Database\Factories\Crm;

use App\Enums\Crm\FormFieldType;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    /**
     * Define the model's default state (an optional text field).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'form_id' => Form::factory(),
            'type' => FormFieldType::Text,
            'name' => ucfirst($name),
            'slug' => Str::slug($name, '_'),
            'is_required' => false,
            'order' => 0,
            'settings' => null,
        ];
    }

    /**
     * A field of the given type, named after it
     */
    public function ofType(FormFieldType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'name' => $type->label(),
            'slug' => Str::slug($type->value, '_'),
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }
}

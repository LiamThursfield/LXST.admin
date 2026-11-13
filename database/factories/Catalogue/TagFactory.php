<?php

namespace Database\Factories\Catalogue;

use App\Enums\Catalogue\TagKind;
use App\Models\Catalogue\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Define the model's default state (one of the tenant's own tags).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'preset' => null,
            'kind' => TagKind::Other,
            'name' => ucfirst(fake()->unique()->word()),
            'icon' => null,
            'order' => 0,
        ];
    }

    /**
     * A built-in tag, e.g. the "milk" allergen
     */
    public function preset(string $preset, TagKind $kind = TagKind::Allergen): static
    {
        return $this->state(fn (array $attributes) => [
            'preset' => $preset,
            'kind' => $kind,
            'name' => ucfirst(str_replace('_', ' ', $preset)),
        ]);
    }

    public function allergen(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => TagKind::Allergen,
        ]);
    }

    public function dietary(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => TagKind::Dietary,
        ]);
    }
}

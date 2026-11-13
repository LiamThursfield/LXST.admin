<?php

namespace Database\Factories\Catalogue;

use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalogue_id' => Catalogue::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => null,
            'order' => 0,
        ];
    }
}

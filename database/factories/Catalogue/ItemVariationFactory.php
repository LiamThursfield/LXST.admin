<?php

namespace Database\Factories\Catalogue;

use App\Models\Catalogue\Item;
use App\Models\Catalogue\ItemVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemVariation>
 */
class ItemVariationFactory extends Factory
{
    protected $model = ItemVariation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'name' => ucfirst(fake()->word()),
            'price' => fake()->numberBetween(3, 40) * 50,
            'stock' => null,
            'order' => 0,
        ];
    }
}

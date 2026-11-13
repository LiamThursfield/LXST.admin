<?php

namespace Database\Factories\Catalogue;

use App\Models\Bookings\Service;
use App\Models\Catalogue\Item;
use App\Models\Catalogue\ItemVariation;
use App\Models\Catalogue\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * Define the model's default state (a priced item, available, not counting stock).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'service_id' => null,
            'image_file_id' => null,
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(3, 40) * 50,
            'is_available' => true,
            'tracks_stock' => false,
            'stock' => null,
            'order' => 0,
        ];
    }

    /**
     * Marked as sold out for now
     */
    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * Counts its stock, with the given number left
     */
    public function withStock(int $stock = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'tracks_stock' => true,
            'stock' => $stock,
        ]);
    }

    /**
     * Shows a bookable service, and uses its price
     */
    public function forService(?Service $service = null): static
    {
        return $this->state(fn (array $attributes) => [
            'service_id' => $service->id ?? Service::factory(),
            'price' => null,
        ]);
    }

    /**
     * Has variations with their own prices, e.g. small and large, so no price of its own
     *
     * @param  array<string, int>  $prices  Each variation's name and price in minor units
     */
    public function withVariations(array $prices = ['Small' => 450, 'Large' => 600]): static
    {
        return $this->state(fn (array $attributes) => ['price' => null])
            ->afterCreating(function (Item $item) use ($prices) {
                $order = 0;

                foreach ($prices as $name => $price) {
                    ItemVariation::factory()->for($item)->create([
                        'name' => $name,
                        'price' => $price,
                        'stock' => $item->tracks_stock ? $item->stock : null,
                        'order' => $order++,
                    ]);
                }

                if ($item->tracks_stock) {
                    $item->update(['stock' => null]);
                }
            });
    }
}

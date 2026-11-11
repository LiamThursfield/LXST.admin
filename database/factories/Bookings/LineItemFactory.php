<?php

namespace Database\Factories\Bookings;

use App\Models\Bookings\LineItem;
use App\Models\Bookings\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineItem>
 */
class LineItemFactory extends Factory
{
    protected $model = LineItem::class;

    /**
     * Define the model's default state (one of something at £50, on a quote).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => (new Quote)->getMorphClass(),
            'document_id' => Quote::factory(),
            'description' => fake()->sentence(3),
            'quantity' => '1.00',
            'unit_price' => 5000,
            'total' => 5000,
            'order' => 0,
        ];
    }
}

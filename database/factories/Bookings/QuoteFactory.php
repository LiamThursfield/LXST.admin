<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\QuoteStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\LineItem;
use App\Models\Bookings\Quote;
use App\Models\Bookings\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * Define the model's default state (a £100 quote sent and waiting for an answer, with no tax).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'QUO-'.fake()->unique()->numerify('9####'),
            'booking_id' => Booking::factory(),
            'status' => QuoteStatus::Sent,
            'subtotal' => 10000,
            'tax_rate' => null,
            'tax' => 0,
            'total' => 10000,
            'currency' => Service::CURRENCY,
            'valid_until' => today()->addDays(30),
            'sent_at' => now(),
        ];
    }

    /**
     * With lines adding up to its subtotal
     */
    public function withLines(): static
    {
        return $this->afterCreating(function (Quote $quote) {
            LineItem::factory()->for($quote, 'document')->create([
                'description' => 'Work as discussed',
                'unit_price' => $quote->subtotal,
                'total' => $quote->subtotal,
            ]);
        });
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Draft, 'sent_at' => null]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Accepted, 'accepted_at' => now()]);
    }

    public function declined(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Declined, 'declined_at' => now()]);
    }

    /**
     * Sent, and past its date without an answer
     */
    public function expired(): static
    {
        return $this->state(fn () => ['valid_until' => today()->subDay()]);
    }

    /**
     * With 20% tax on top
     */
    public function withTax(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_rate' => '20.00',
            'tax' => (int) round($attributes['subtotal'] * 0.2),
            'total' => $attributes['subtotal'] + (int) round($attributes['subtotal'] * 0.2),
        ]);
    }
}

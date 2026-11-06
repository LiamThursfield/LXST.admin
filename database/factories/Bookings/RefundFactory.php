<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\RefundStatus;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * Define the model's default state (part of an online payment given back, with a made-up Stripe refund).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'amount' => 500,
            'status' => RefundStatus::Succeeded,
            'stripe_refund_id' => 're_test_'.Str::random(24),
            'refunded_at' => now(),
        ];
    }

    /**
     * Still waiting on the bank
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => RefundStatus::Pending, 'refunded_at' => null]);
    }

    /**
     * Given back by hand, for a payment taken in person
     */
    public function inPerson(): static
    {
        return $this->state(fn () => ['stripe_refund_id' => null]);
    }
}

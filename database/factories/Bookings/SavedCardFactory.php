<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\SavedCardStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\SavedCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SavedCard>
 */
class SavedCardFactory extends Factory
{
    protected $model = SavedCard::class;

    /**
     * Define the model's default state (a card saved, with made-up Stripe IDs).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'status' => SavedCardStatus::Saved,
            'stripe_checkout_session_id' => 'cs_test_'.Str::random(24),
            'stripe_customer_id' => 'cus_test_'.Str::random(14),
            'stripe_payment_method_id' => 'pm_test_'.Str::random(24),
            'brand' => 'visa',
            'last4' => '4242',
            'saved_at' => now(),
        ];
    }

    /**
     * A link sent, with no card saved yet
     */
    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SavedCardStatus::Requested,
            'checkout_url' => 'https://checkout.stripe.com/c/pay/'.($attributes['stripe_checkout_session_id'] ?? 'cs_test'),
            'stripe_payment_method_id' => null,
            'brand' => null,
            'last4' => null,
            'saved_at' => null,
            'expires_at' => now()->addDay(),
        ]);
    }
}

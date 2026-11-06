<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentPurpose;
use App\Enums\Bookings\PaymentStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state (a paid online payment, with a made-up Stripe checkout).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'purpose' => PaymentPurpose::Booking,
            'amount' => 1000,
            'currency' => Service::CURRENCY,
            'status' => PaymentStatus::Paid,
            'method' => PaymentMethod::Online,
            'application_fee' => 0,
            'stripe_checkout_session_id' => 'cs_test_'.Str::random(24),
            'stripe_payment_intent_id' => 'pi_test_'.Str::random(24),
            'paid_at' => now(),
        ];
    }

    /**
     * A link sent and not paid yet
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Pending,
            'checkout_url' => 'https://checkout.stripe.com/c/pay/'.($attributes['stripe_checkout_session_id'] ?? 'cs_test'),
            'expires_at' => now()->addDay(),
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
        ]);
    }

    /**
     * Taken in person
     */
    public function inPerson(PaymentMethod $method = PaymentMethod::Cash): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => $method,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
        ]);
    }

    /**
     * A no-show or late cancellation fee, charged to a saved card
     */
    public function fee(PaymentPurpose $purpose = PaymentPurpose::NoShowFee): static
    {
        return $this->state(fn () => [
            'purpose' => $purpose,
            'stripe_checkout_session_id' => null,
        ]);
    }
}

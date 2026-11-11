<?php

namespace Database\Factories\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\GiftCardStatus;
use App\Enums\Bookings\PaymentMethod;
use App\Models\Bookings\GiftCard;
use App\Models\Bookings\Service;
use App\Services\Bookings\GiftCards;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GiftCard>
 */
class GiftCardFactory extends Factory
{
    protected $model = GiftCard::class;

    /**
     * Define the model's default state (a £50 gift card sold in person, ready to use for a year).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => GiftCards::formatCode(Str::upper(Str::random(12))),
            'kind' => GiftCardKind::Amount,
            'status' => GiftCardStatus::Active,
            'amount' => 5000,
            'balance' => 5000,
            'currency' => Service::CURRENCY,
            'price_paid' => 5000,
            'payment_method' => PaymentMethod::Card,
            'purchaser_name' => fake()->name(),
            'purchaser_email' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'recipient_email' => fake()->safeEmail(),
            'expires_on' => today()->addYear(),
            'paid_at' => now(),
        ];
    }

    /**
     * Worth an amount, with some of it already used
     */
    public function worth(int $amount, ?int $balance = null): static
    {
        return $this->state(fn () => [
            'amount' => $amount,
            'balance' => $balance ?? $amount,
            'price_paid' => $amount,
        ]);
    }

    /**
     * A voucher for one booking of a service, worth its price
     */
    public function forService(?Service $service = null): static
    {
        return $this->state(function () use ($service) {
            $service ??= Service::factory()->create();
            $price = (int) $service->price;

            return [
                'kind' => GiftCardKind::Service,
                'service_id' => $service->id,
                'amount' => $price,
                'balance' => $price,
                'price_paid' => $price,
            ];
        });
    }

    /**
     * Sold online, its payment link sent and not paid yet
     */
    public function pending(): static
    {
        return $this->state(function () {
            $session = 'cs_test_'.Str::random(24);

            return [
                'status' => GiftCardStatus::Pending,
                'payment_method' => PaymentMethod::Online,
                'stripe_checkout_session_id' => $session,
                'checkout_url' => 'https://checkout.stripe.com/c/pay/'.$session,
                'link_expires_at' => now()->addDay(),
                'paid_at' => null,
            ];
        });
    }

    /**
     * Given free
     */
    public function free(): static
    {
        return $this->state(fn () => ['payment_method' => null, 'price_paid' => 0]);
    }

    /**
     * All used up
     */
    public function used(): static
    {
        return $this->state(fn () => ['status' => GiftCardStatus::Used, 'balance' => 0]);
    }

    /**
     * Past its last day
     */
    public function expired(): static
    {
        return $this->state(fn () => ['expires_on' => today()->subDay()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => GiftCardStatus::Cancelled]);
    }
}

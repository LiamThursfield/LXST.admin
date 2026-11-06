<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\DepositType;
use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentRequirement;
use App\Enums\Bookings\PaymentStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Industry\Terminology;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What's been paid towards a booking and what's left, asking for it online through the tenant's own
 * Stripe account, and recording what's taken in person
 */
class BookingPayments
{
    /** Symbols for the currencies most tenants will use; any other is written as its code */
    protected const array SYMBOLS = ['GBP' => '£', 'EUR' => '€', 'USD' => '$'];

    /** Stripe's smallest charge, in minor units */
    public const int MINIMUM = 30;

    /** How long a payment link lasts, which is Stripe's longest */
    protected const int LINK_HOURS = 24;

    public function __construct(
        protected StripeGateway $stripe,
        protected BookingMessenger $messenger,
        protected Terminology $terminology,
    ) {}

    public function paid(Booking $booking): int
    {
        return (int) $booking->payments()->where('status', PaymentStatus::Paid)->sum('amount');
    }

    /**
     * What's left to pay, or null when there's no price, e.g. on quote
     */
    public function outstanding(Booking $booking): ?int
    {
        return $booking->price === null ? null : max(0, $booking->price - $this->paid($booking));
    }

    /**
     * What to ask for next: the deposit if the service wants one and nothing's paid yet, otherwise
     * what's left
     */
    public function suggested(Booking $booking): ?int
    {
        $outstanding = $this->outstanding($booking);

        if ($outstanding === null || $outstanding === 0) {
            return $outstanding;
        }

        $deposit = $this->depositFor($booking->service, (int) $booking->price);

        return $deposit !== null && $this->paid($booking) === 0 ? min($deposit, $outstanding) : $outstanding;
    }

    /**
     * The deposit a service asks for on a price, or null if it doesn't ask for one
     */
    public function depositFor(?Service $service, int $price): ?int
    {
        if ($service === null || $service->payment_requirement !== PaymentRequirement::Deposit || $service->deposit_amount === null) {
            return null;
        }

        return $service->deposit_type === DepositType::Percent
            ? (int) round($price * $service->deposit_amount / 100)
            : min($price, $service->deposit_amount);
    }

    /**
     * Creates a Stripe payment link for an amount, and sends it to the customer
     *
     * @throws ValidationException When payments can't be taken, or the amount isn't allowed
     */
    public function request(Booking $booking, int $amount, ?User $by = null): Payment
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || ! $tenant->canTakePayments() || ! $this->stripe->isConfigured()) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.not_set_up')]);
        }

        $this->checkAmount($booking, $amount);

        $payment = DB::transaction(function () use ($booking, $amount, $by, $tenant) {
            $payment = $booking->payments()->create([
                'amount' => $amount,
                'currency' => Service::CURRENCY,
                'status' => PaymentStatus::Pending,
                'method' => PaymentMethod::Online,
                'application_fee' => $tenant->platformFeeFor($amount),
                'expires_at' => now()->addHours(self::LINK_HOURS),
                'created_by_id' => $by?->id,
            ]);

            $checkout = $this->stripe->createCheckout(
                accountId: (string) $tenant->getAttribute('stripe_account_id'),
                amount: $amount,
                currency: Service::CURRENCY,
                description: "{$booking->service_name}, {$booking->customer_name}",
                applicationFee: $payment->application_fee,
                customerEmail: $booking->customer_email,
                // Back from Stripe, the page says how it went rather than sending them to pay again
                successUrl: route('bookings.payments.show', ['publicId' => $payment->public_id, 'back' => 1]),
                cancelUrl: route('bookings.payments.show', ['publicId' => $payment->public_id, 'back' => 1]),
                metadata: ['tenant_id' => (string) $tenant->getTenantKey(), 'payment_id' => (string) $payment->id],
            );

            $payment->update(['stripe_checkout_session_id' => $checkout['id'], 'checkout_url' => $checkout['url']]);

            return $payment;
        });

        $this->messenger->sendCustom($booking, $this->terminology->translate('bookings.payments.request_message', [
            'amount' => $this->format($amount),
            'link' => route('bookings.payments.show', $payment->public_id),
        ]));

        return $payment;
    }

    /**
     * Records money taken in person
     *
     * @throws ValidationException When the amount isn't allowed
     */
    public function record(Booking $booking, int $amount, PaymentMethod $method, ?string $note, ?User $by = null): Payment
    {
        $this->checkAmount($booking, $amount, minimum: 1);

        return $booking->payments()->create([
            'amount' => $amount,
            'currency' => Service::CURRENCY,
            'status' => PaymentStatus::Paid,
            'method' => $method,
            'note' => $note,
            'paid_at' => now(),
            'created_by_id' => $by?->id,
        ]);
    }

    /**
     * Updates a payment from Stripe's news of its checkout. Safe to hear more than once.
     */
    public function settle(string $checkoutSessionId, PaymentStatus $status, ?string $paymentIntentId = null): ?Payment
    {
        $payment = Payment::query()->firstWhere('stripe_checkout_session_id', $checkoutSessionId);

        // A payment once paid stays paid, whatever arrives late or twice
        if ($payment === null || $payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        $payment->update(array_filter([
            'status' => $status,
            'stripe_payment_intent_id' => $paymentIntentId,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
        ], fn (mixed $value) => $value !== null));

        return $payment;
    }

    /**
     * An amount as people write it, e.g. £12.50. Done by hand, as the intl extension isn't everywhere.
     */
    public function format(int $amount, string $currency = Service::CURRENCY): string
    {
        $symbol = self::SYMBOLS[$currency] ?? null;
        $number = number_format(abs($amount) / 100, 2);
        $sign = $amount < 0 ? '-' : '';

        return $symbol === null ? "{$sign}{$number} {$currency}" : "{$sign}{$symbol}{$number}";
    }

    /**
     * @throws ValidationException
     */
    protected function checkAmount(Booking $booking, int $amount, int $minimum = self::MINIMUM): void
    {
        $outstanding = $this->outstanding($booking);

        if ($amount < $minimum) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.too_small', ['amount' => $this->format($minimum)])]);
        }

        if ($outstanding !== null && $amount > $outstanding) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.more_than_owed', ['amount' => $this->format($outstanding)])]);
        }
    }
}

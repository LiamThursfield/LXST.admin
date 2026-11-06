<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\DepositType;
use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentPurpose;
use App\Enums\Bookings\PaymentRequirement;
use App\Enums\Bookings\PaymentStatus;
use App\Enums\Bookings\RefundStatus;
use App\Enums\Bookings\SavedCardStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Refund;
use App\Models\Bookings\SavedCard;
use App\Models\Bookings\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Industry\Terminology;
use App\Services\Payments\StripeGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Throwable;

/**
 * What's been paid towards a booking and what's left, asking for it online through the tenant's own
 * Stripe account, recording what's taken in person, refunds, and cards kept for no-show and late
 * cancellation fees
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

    /**
     * What's been paid towards the price, less anything given back. Fees don't count towards it.
     */
    public function paid(Booking $booking): int
    {
        $payments = $booking->payments()->where('purpose', PaymentPurpose::Booking)->where('status', PaymentStatus::Paid);

        $refunded = Refund::query()->whereIn('payment_id', (clone $payments)->select('id'))->where('status', '!=', RefundStatus::Failed)->sum('amount');

        return (int) $payments->sum('amount') - (int) $refunded;
    }

    /**
     * What's been given back from a payment, or is on its way
     */
    public function refunded(Payment $payment): int
    {
        $refunds = $payment->relationLoaded('refunds') ? $payment->refunds : $payment->refunds()->get();

        return (int) $refunds->filter(fn (Refund $refund) => $refund->status !== RefundStatus::Failed)->sum('amount');
    }

    /**
     * What can still be given back from a payment
     */
    public function refundable(Payment $payment): int
    {
        return $payment->status === PaymentStatus::Paid ? max(0, $payment->amount - $this->refunded($payment)) : 0;
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
        $tenant = $this->tenantTakingPayments();

        $this->checkAmount($booking, $amount);

        $payment = DB::transaction(function () use ($booking, $amount, $by, $tenant) {
            $payment = $booking->payments()->create([
                'purpose' => PaymentPurpose::Booking,
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
                // Keeps the card too, if the service has fees to charge later and there isn't one yet
                saveCard: $this->hasFees($booking->service) && $this->savedCard($booking) === null,
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
            'purpose' => PaymentPurpose::Booking,
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

        if ($status === PaymentStatus::Paid && $paymentIntentId !== null) {
            $this->keepCardFrom($payment, $paymentIntentId);
        }

        return $payment;
    }

    /**
     * Updates a charge to a saved card that Stripe was still working on. Safe to hear more than once.
     */
    public function settleIntent(string $paymentIntentId, PaymentStatus $status, ?string $failureMessage = null): ?Payment
    {
        $payment = Payment::query()->where('stripe_payment_intent_id', $paymentIntentId)->where('status', PaymentStatus::Pending)->first();

        $payment?->update([
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            'failure_message' => $failureMessage,
        ]);

        return $payment;
    }

    /**
     * Gives back some or all of a payment: through Stripe for one made online, with the platform's
     * share of its fee, or noted for one taken in person
     *
     * @throws ValidationException When the amount isn't allowed, or Stripe refuses
     */
    public function refund(Payment $payment, int $amount, ?string $reason, ?User $by = null): Refund
    {
        $refundable = $this->refundable($payment);

        if ($refundable === 0) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.nothing_to_refund')]);
        }

        if ($amount < 1 || $amount > $refundable) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.more_than_refundable', ['amount' => $this->format($refundable)])]);
        }

        $attributes = [
            'amount' => $amount,
            'status' => RefundStatus::Succeeded,
            'reason' => $reason,
            'refunded_at' => now(),
            'created_by_id' => $by?->id,
        ];

        if ($payment->method === PaymentMethod::Online) {
            $accountId = tenant()?->getAttribute('stripe_account_id');

            if (! is_string($accountId) || $payment->stripe_payment_intent_id === null || ! $this->stripe->isConfigured()) {
                throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.refund_not_possible')]);
            }

            try {
                $stripeRefund = $this->stripe->refund($accountId, $payment->stripe_payment_intent_id, $amount, ['payment_id' => (string) $payment->id]);
            } catch (ApiErrorException $exception) {
                throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.stripe_refused', ['message' => $exception->getMessage()])]);
            }

            $status = RefundStatus::fromStripe($stripeRefund['status']);

            $attributes = [
                ...$attributes,
                'status' => $status,
                'stripe_refund_id' => $stripeRefund['id'],
                // Stripe gives back the same share of the platform fee as of the payment
                'application_fee_refunded' => $payment->amount > 0 ? (int) round($payment->application_fee * $amount / $payment->amount) : 0,
                'refunded_at' => $status === RefundStatus::Succeeded ? now() : null,
            ];
        }

        return $payment->refunds()->create($attributes);
    }

    /**
     * Updates a refund from Stripe's news of it, e.g. the bank turning it down
     */
    public function settleRefund(string $stripeRefundId, ?string $stripeStatus): ?Refund
    {
        $refund = Refund::query()->firstWhere('stripe_refund_id', $stripeRefundId);
        $status = RefundStatus::fromStripe($stripeStatus);

        $refund?->update([
            'status' => $status,
            'refunded_at' => $status === RefundStatus::Succeeded ? ($refund->refunded_at ?? now()) : null,
        ]);

        return $refund;
    }

    /**
     * The card most recently kept for this booking's fees
     */
    public function savedCard(Booking $booking): ?SavedCard
    {
        return $booking->savedCards()->where('status', SavedCardStatus::Saved)->latest('id')->first();
    }

    /**
     * Whether a service has a no-show or late cancellation fee
     */
    public function hasFees(?Service $service): bool
    {
        return $service !== null && ($service->no_show_fee_amount !== null || $service->late_cancellation_fee_amount !== null);
    }

    /**
     * The fee a booking's service asks for, worked out on its price, or null if it doesn't ask for one
     */
    public function feeFor(Booking $booking, PaymentPurpose $purpose): ?int
    {
        $service = $booking->service;

        [$type, $amount] = match ($purpose) {
            PaymentPurpose::NoShowFee => [$service?->no_show_fee_type, $service?->no_show_fee_amount],
            PaymentPurpose::LateCancellationFee => [$service?->late_cancellation_fee_type, $service?->late_cancellation_fee_amount],
            PaymentPurpose::Booking => [null, null],
        };

        if ($amount === null) {
            return null;
        }

        if ($type === DepositType::Percent) {
            return $booking->price === null ? null : (int) round($booking->price * $amount / 100);
        }

        return $amount;
    }

    /**
     * The fee that could be charged now: no-show for a no-show, late cancellation for one cancelled
     * inside the window, and only if it hasn't been charged already
     */
    public function chargeableFee(Booking $booking): ?PaymentPurpose
    {
        $purpose = match (true) {
            $booking->status === BookingStatus::NoShow => PaymentPurpose::NoShowFee,
            $booking->status === BookingStatus::Cancelled && $booking->is_late_cancellation => PaymentPurpose::LateCancellationFee,
            default => null,
        };

        if ($purpose === null || $booking->payments()->where('purpose', $purpose)->whereIn('status', [PaymentStatus::Paid, PaymentStatus::Pending])->exists()) {
            return null;
        }

        return $purpose;
    }

    /**
     * Sends the customer a link to save a card for later fees
     *
     * @throws ValidationException When payments can't be taken
     */
    public function requestCard(Booking $booking, ?User $by = null): SavedCard
    {
        $tenant = $this->tenantTakingPayments();
        $accountId = (string) $tenant->getAttribute('stripe_account_id');

        $card = DB::transaction(function () use ($booking, $by, $tenant, $accountId) {
            // The same customer on Stripe for every card on this booking
            $customerId = $booking->savedCards()->whereNotNull('stripe_customer_id')->latest('id')->value('stripe_customer_id')
                ?? $this->stripe->createCustomer($accountId, $booking->customer_name, $booking->customer_email);

            $card = $booking->savedCards()->create([
                'status' => SavedCardStatus::Requested,
                'stripe_customer_id' => $customerId,
                'expires_at' => now()->addHours(self::LINK_HOURS),
                'created_by_id' => $by?->id,
            ]);

            $checkout = $this->stripe->createCardSetup(
                accountId: $accountId,
                customerId: (string) $customerId,
                currency: Service::CURRENCY,
                successUrl: route('bookings.cards.show', ['publicId' => $card->public_id, 'back' => 1]),
                cancelUrl: route('bookings.cards.show', ['publicId' => $card->public_id, 'back' => 1]),
                metadata: ['tenant_id' => (string) $tenant->getTenantKey(), 'saved_card_id' => (string) $card->id],
            );

            $card->update(['stripe_checkout_session_id' => $checkout['id'], 'checkout_url' => $checkout['url']]);

            return $card;
        });

        $this->messenger->sendCustom($booking, $this->terminology->translate('bookings.payments.cards.request_message', [
            'link' => route('bookings.cards.show', $card->public_id),
        ]));

        return $card;
    }

    /**
     * Updates a card from Stripe's news of its setup page. Safe to hear more than once.
     */
    public function settleCard(string $checkoutSessionId, SavedCardStatus $status, ?string $setupIntentId = null): ?SavedCard
    {
        $card = SavedCard::query()->firstWhere('stripe_checkout_session_id', $checkoutSessionId);

        if ($card === null || $card->status === SavedCardStatus::Saved) {
            return $card;
        }

        if ($status !== SavedCardStatus::Saved) {
            $card->update(['status' => $status]);

            return $card;
        }

        $accountId = tenant()?->getAttribute('stripe_account_id');
        $details = is_string($accountId) ? $this->stripe->savedCard($accountId, setupIntentId: $setupIntentId) : null;

        $card->update([
            'status' => SavedCardStatus::Saved,
            'stripe_customer_id' => $details['customer'] ?? $card->stripe_customer_id,
            'stripe_payment_method_id' => $details['payment_method'] ?? null,
            'brand' => $details['brand'] ?? null,
            'last4' => $details['last4'] ?? null,
            'saved_at' => now(),
        ]);

        return $card;
    }

    /**
     * Charges a no-show or late cancellation fee to the card saved for the booking
     *
     * @throws ValidationException When the fee can't be charged, or the card is declined
     */
    public function chargeFee(Booking $booking, int $amount, ?User $by = null): Payment
    {
        $tenant = $this->tenantTakingPayments();
        $purpose = $this->chargeableFee($booking);
        $card = $this->savedCard($booking);

        if ($purpose === null) {
            throw ValidationException::withMessages(['amount' => $this->terminology->translate('bookings.payments.validation.no_fee_due')]);
        }

        if ($card === null || $card->stripe_payment_method_id === null) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.no_card')]);
        }

        if ($amount < self::MINIMUM) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.too_small', ['amount' => $this->format(self::MINIMUM)])]);
        }

        $payment = $booking->payments()->create([
            'purpose' => $purpose,
            'amount' => $amount,
            'currency' => Service::CURRENCY,
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::Online,
            'application_fee' => $tenant->platformFeeFor($amount),
            'created_by_id' => $by?->id,
        ]);

        $charge = $this->stripe->chargeSavedCard(
            accountId: (string) $tenant->getAttribute('stripe_account_id'),
            customerId: (string) $card->stripe_customer_id,
            paymentMethodId: $card->stripe_payment_method_id,
            amount: $amount,
            currency: Service::CURRENCY,
            applicationFee: $payment->application_fee,
            description: "{$purpose->label()}: {$booking->service_name}, {$booking->customer_name}",
            metadata: ['tenant_id' => (string) $tenant->getTenantKey(), 'payment_id' => (string) $payment->id],
        );

        $status = match ($charge['status']) {
            'succeeded' => PaymentStatus::Paid,
            'processing' => PaymentStatus::Pending,
            default => PaymentStatus::Failed,
        };

        $payment->update([
            'status' => $status,
            'stripe_payment_intent_id' => $charge['id'],
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            'failure_message' => $charge['error'],
        ]);

        if ($status === PaymentStatus::Failed) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.card_declined', ['message' => $charge['error'] ?? __('bookings.payments.validation.card_declined_default')])]);
        }

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
     * The tenant, if it can take payments online
     *
     * @throws ValidationException When it can't
     */
    protected function tenantTakingPayments(): Tenant
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || ! $tenant->canTakePayments() || ! $this->stripe->isConfigured()) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.not_set_up')]);
        }

        return $tenant;
    }

    /**
     * Keeps the card a customer paid with, when the payment was set to save it. A failure to fetch
     * it from Stripe is reported, not thrown, as the payment itself has gone through.
     */
    protected function keepCardFrom(Payment $payment, string $paymentIntentId): void
    {
        $booking = $payment->booking;
        $accountId = tenant()?->getAttribute('stripe_account_id');

        if ($payment->purpose !== PaymentPurpose::Booking || ! $this->hasFees($booking->service) || ! is_string($accountId) || $this->savedCard($booking) !== null) {
            return;
        }

        try {
            $details = $this->stripe->savedCard($accountId, paymentIntentId: $paymentIntentId);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($details === null) {
            return;
        }

        $booking->savedCards()->create([
            'status' => SavedCardStatus::Saved,
            'stripe_customer_id' => $details['customer'],
            'stripe_payment_method_id' => $details['payment_method'],
            'brand' => $details['brand'],
            'last4' => $details['last4'],
            'saved_at' => now(),
        ]);
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

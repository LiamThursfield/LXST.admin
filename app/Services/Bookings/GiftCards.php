<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Enums\Bookings\GiftCardStatus;
use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentPurpose;
use App\Enums\Bookings\PaymentStatus;
use App\Enums\Bookings\RefundStatus;
use App\Mail\Bookings\GiftCardMail;
use App\Mail\Bookings\GiftCardPaymentMail;
use App\Models\Bookings\Booking;
use App\Models\Bookings\GiftCard;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Service;
use App\Models\Settings\BookingsSettings;
use App\Models\Settings\GeneralSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\StripeGateway;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gift cards and vouchers: issuing and selling them, by staff or online through the tenant's own
 * Stripe account, and taking payments for bookings from them by their code
 */
class GiftCards
{
    /** Letters and numbers that can't be mistaken for each other when read out or typed */
    protected const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** How long a link to pay for one lasts, which is Stripe's longest */
    protected const int LINK_HOURS = 24;

    /** The least and most a customer can buy one for online, in minor units */
    public const int ONLINE_MINIMUM = 500;

    public const int ONLINE_MAXIMUM = 100000;

    public function __construct(
        protected StripeGateway $stripe,
        protected BookingPayments $payments,
        protected BookingsSettings $settings,
    ) {}

    /**
     * A code in groups of four, e.g. ABCD-EFGH-JKLM, however it was typed
     */
    public static function formatCode(string $code): string
    {
        $plain = (string) preg_replace('/[^A-Z0-9]/', '', Str::upper($code));

        return implode('-', str_split($plain, 4));
    }

    /**
     * The last day a card issued today can be used, from the business's setting
     */
    public function defaultExpiry(): ?CarbonInterface
    {
        $months = $this->settings->gift_card_months;

        return $months === null || $months < 1 ? null : today()->addMonthsNoOverflow($months);
    }

    /**
     * What a card is worth, e.g. £50.00, or the service it's for
     */
    public function valueLabel(GiftCard $card): string
    {
        return $card->kind === GiftCardKind::Service
            ? (string) ($card->service->name ?? __('bookings.gift_cards.deleted_service'))
            : $this->payments->format($card->amount, $card->currency);
    }

    /**
     * What's left on a card, e.g. £20.00, or whether a voucher is still to use
     */
    public function balanceLabel(GiftCard $card): string
    {
        if ($card->kind === GiftCardKind::Service) {
            return $card->balance > 0 ? __('bookings.gift_cards.not_used') : __('bookings.gift_cards.used');
        }

        return $this->payments->format($card->balance, $card->currency);
    }

    /**
     * Issues a card from the admin: sold in person, given free, or sold with a link to pay online
     * sent to the buyer. One paid for or given is sent to whoever it's for straight away.
     *
     * @param  array{kind: GiftCardKind, service: Service|null, amount: int|null, method: PaymentMethod|null, purchaser_name: string|null, purchaser_email: string|null, recipient_name: string|null, recipient_email: string|null, message: string|null, expires_on: CarbonInterface|null}  $details
     *
     * @throws ValidationException When it's sold online and payments can't be taken
     */
    public function issue(array $details, ?User $by = null): GiftCard
    {
        $value = $this->valueOf($details['kind'], $details['service'], $details['amount']);

        if ($details['method'] === PaymentMethod::Online) {
            $card = $this->sellOnline($details, $value, $by);

            Mail::to((string) $card->purchaser_email)->queue(new GiftCardPaymentMail(
                business: $this->business(),
                value: $this->valueLabel($card),
                price: $this->payments->format($card->price_paid, $card->currency),
                link: route('bookings.gift-cards.show', $card->public_id),
            ));

            return $card;
        }

        $card = GiftCard::query()->create([
            ...$this->attributes($details, $value),
            'status' => GiftCardStatus::Active,
            'price_paid' => $details['method'] === null ? 0 : $value,
            'payment_method' => $details['method'],
            'paid_at' => $details['method'] === null ? null : now(),
            'created_by_id' => $by?->id,
        ]);

        $this->send($card);

        return $card;
    }

    /**
     * A card bought on the public page, waiting for the customer to pay on Stripe
     *
     * @param  array{kind: GiftCardKind, service: Service|null, amount: int|null, purchaser_name: string, purchaser_email: string, recipient_name: string|null, recipient_email: string|null, message: string|null}  $details
     *
     * @throws ValidationException When payments can't be taken
     */
    public function buy(array $details): GiftCard
    {
        $value = $this->valueOf($details['kind'], $details['service'], $details['amount']);

        return $this->sellOnline([...$details, 'expires_on' => $this->defaultExpiry()], $value);
    }

    /**
     * Updates a card from Stripe's news of its checkout, sending it once paid. Safe to hear more than once.
     */
    public function settle(string $checkoutSessionId, PaymentStatus $status, ?string $paymentIntentId = null): ?GiftCard
    {
        $card = GiftCard::query()->firstWhere('stripe_checkout_session_id', $checkoutSessionId);

        // Only a card waiting to be paid for changes, so a cancelled one stays cancelled
        if ($card === null || $card->status !== GiftCardStatus::Pending || $status === PaymentStatus::Pending) {
            return $card;
        }

        if ($status !== PaymentStatus::Paid) {
            $card->update(['status' => GiftCardStatus::Expired]);

            return $card;
        }

        $card->update([
            'status' => GiftCardStatus::Active,
            'stripe_payment_intent_id' => $paymentIntentId,
            'paid_at' => now(),
        ]);

        $this->send($card);

        return $card;
    }

    /**
     * Emails the card to whoever it's for, or the buyer if there's no one else
     */
    public function send(GiftCard $card): bool
    {
        $to = $card->recipient_email ?? $card->purchaser_email;

        if (blank($to) || ! in_array($card->currentStatus(), [GiftCardStatus::Active, GiftCardStatus::Used], true)) {
            return false;
        }

        Mail::to((string) $to)->queue(new GiftCardMail(
            business: $this->business(),
            value: $this->valueLabel($card),
            code: $card->code,
            recipientName: $card->recipient_name,
            purchaserName: $card->purchaser_name,
            personalMessage: $card->message,
            expiresOn: $card->expires_on?->format('j F Y'),
            link: route('bookings.gift-cards.show', $card->public_id),
        ));

        $card->update(['sent_at' => now()]);

        return true;
    }

    /**
     * Stops a card being used, or paid for if it's still waiting
     *
     * @throws ValidationException When it's already used or cancelled
     */
    public function cancel(GiftCard $card): GiftCard
    {
        if (! in_array($card->status, [GiftCardStatus::Active, GiftCardStatus::Pending], true)) {
            throw ValidationException::withMessages(['card' => __('bookings.gift_cards.validation.cannot_cancel')]);
        }

        $card->update(['status' => GiftCardStatus::Cancelled]);

        return $card;
    }

    /**
     * Takes a payment for a booking from a card. A voucher pays what's left in full, and only for its
     * service; a card with an amount pays up to what's on it.
     *
     * @throws ValidationException When the code can't be used, or the amount isn't allowed
     */
    public function redeem(Booking $booking, string $code, ?int $amount, ?User $by = null): Payment
    {
        return DB::transaction(function () use ($booking, $code, $amount, $by) {
            $card = GiftCard::query()->with('service')->where('code', self::formatCode($code))->lockForUpdate()->first();

            if ($card === null) {
                throw ValidationException::withMessages(['code' => __('bookings.gift_cards.validation.not_found')]);
            }

            $this->checkUsable($card);

            $outstanding = $this->payments->outstanding($booking);

            if ($card->kind === GiftCardKind::Service) {
                if ($booking->service_id !== $card->service_id) {
                    throw ValidationException::withMessages(['code' => __('bookings.gift_cards.validation.other_service', ['service' => $this->valueLabel($card)])]);
                }

                if ($outstanding === null || $outstanding === 0) {
                    throw ValidationException::withMessages(['code' => __('bookings.gift_cards.validation.nothing_owed')]);
                }

                $amount = $outstanding;
                $card->balance = 0;
            } else {
                $amount ??= $outstanding === null ? $card->balance : min($card->balance, $outstanding);

                if ($outstanding === 0) {
                    throw ValidationException::withMessages(['code' => __('bookings.gift_cards.validation.nothing_owed')]);
                }

                if ($amount < 1 || $amount > $card->balance) {
                    throw ValidationException::withMessages(['amount' => __('bookings.gift_cards.validation.more_than_balance', ['amount' => $this->payments->format($card->balance)])]);
                }

                if ($outstanding !== null && $amount > $outstanding) {
                    throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.more_than_owed', ['amount' => $this->payments->format($outstanding)])]);
                }

                $card->balance -= $amount;
            }

            $card->status = $card->balance === 0 ? GiftCardStatus::Used : GiftCardStatus::Active;
            $card->save();

            return $booking->payments()->create([
                'gift_card_id' => $card->id,
                'purpose' => PaymentPurpose::Booking,
                'amount' => $amount,
                'currency' => $card->currency,
                'status' => PaymentStatus::Paid,
                'method' => PaymentMethod::GiftCard,
                'note' => $card->code,
                'paid_at' => now(),
                'created_by_id' => $by?->id,
            ]);
        });
    }

    /**
     * Puts a refunded payment back on the card it was taken from. A voucher is ready to use again
     * once its whole payment has been given back.
     */
    public function restore(Payment $payment, int $amount): void
    {
        DB::transaction(function () use ($payment, $amount) {
            $card = GiftCard::query()->whereKey($payment->gift_card_id)->lockForUpdate()->first();

            if ($card === null) {
                return;
            }

            if ($card->kind === GiftCardKind::Service) {
                $refunded = (int) $payment->refunds()->where('status', '!=', RefundStatus::Failed)->sum('amount');

                if ($refunded < $payment->amount) {
                    return;
                }

                $card->balance = $card->amount;
            } else {
                $card->balance = min($card->amount, $card->balance + $amount);
            }

            if ($card->status === GiftCardStatus::Used) {
                $card->status = GiftCardStatus::Active;
            }

            $card->save();
        });
    }

    /**
     * @throws ValidationException When the card can't be used
     */
    protected function checkUsable(GiftCard $card): void
    {
        $message = match ($card->currentStatus()) {
            GiftCardStatus::Pending => __('bookings.gift_cards.validation.not_paid'),
            GiftCardStatus::Cancelled => __('bookings.gift_cards.validation.cancelled'),
            GiftCardStatus::Expired => $card->expires_on === null
                ? __('bookings.gift_cards.validation.not_paid')
                : __('bookings.gift_cards.validation.expired', ['date' => $card->expires_on->format('j M Y')]),
            GiftCardStatus::Used => __('bookings.gift_cards.validation.used'),
            GiftCardStatus::Active => $card->balance > 0 ? null : __('bookings.gift_cards.validation.used'),
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['code' => $message]);
        }
    }

    /**
     * A card waiting to be paid for on Stripe
     *
     * @param  array{kind: GiftCardKind, service: Service|null, amount: int|null, purchaser_name: string|null, purchaser_email: string|null, recipient_name: string|null, recipient_email: string|null, message: string|null, expires_on: CarbonInterface|null}  $details
     *
     * @throws ValidationException When payments can't be taken
     */
    protected function sellOnline(array $details, int $value, ?User $by = null): GiftCard
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant || ! $tenant->canTakePayments() || ! $this->stripe->isConfigured()) {
            throw ValidationException::withMessages(['method' => __('bookings.payments.validation.not_set_up')]);
        }

        if ($value < BookingPayments::MINIMUM) {
            throw ValidationException::withMessages(['amount' => __('bookings.payments.validation.too_small', ['amount' => $this->payments->format(BookingPayments::MINIMUM)])]);
        }

        return DB::transaction(function () use ($details, $value, $by, $tenant) {
            $card = GiftCard::query()->create([
                ...$this->attributes($details, $value),
                'status' => GiftCardStatus::Pending,
                'price_paid' => $value,
                'payment_method' => PaymentMethod::Online,
                'application_fee' => $tenant->platformFeeFor($value),
                'link_expires_at' => now()->addHours(self::LINK_HOURS),
                'created_by_id' => $by?->id,
            ]);

            $back = route('bookings.gift-cards.show', ['publicId' => $card->public_id, 'back' => 1]);

            $checkout = $this->stripe->createCheckout(
                accountId: (string) $tenant->getAttribute('stripe_account_id'),
                amount: $value,
                currency: $card->currency,
                description: __('bookings.gift_cards.checkout_description', ['value' => $this->valueLabel($card)]),
                applicationFee: $card->application_fee,
                customerEmail: $card->purchaser_email,
                successUrl: $back,
                cancelUrl: $back,
                metadata: ['tenant_id' => (string) $tenant->getTenantKey(), 'gift_card_id' => (string) $card->id],
            );

            $card->update(['stripe_checkout_session_id' => $checkout['id'], 'checkout_url' => $checkout['url']]);

            return $card;
        });
    }

    /**
     * What a new card is worth: the amount chosen, or the service's price
     *
     * @throws ValidationException When a service has no set price
     */
    protected function valueOf(GiftCardKind $kind, ?Service $service, ?int $amount): int
    {
        if ($kind === GiftCardKind::Amount) {
            return (int) $amount;
        }

        if ($service === null || $service->price === null || $service->price < 1) {
            throw ValidationException::withMessages(['service_id' => __('bookings.gift_cards.validation.service_price')]);
        }

        return $service->price;
    }

    /**
     * @param  array{kind: GiftCardKind, service: Service|null, purchaser_name: string|null, purchaser_email: string|null, recipient_name: string|null, recipient_email: string|null, message: string|null, expires_on: CarbonInterface|null}  $details
     * @return array<string, mixed>
     */
    protected function attributes(array $details, int $value): array
    {
        return [
            'code' => $this->newCode(),
            'kind' => $details['kind'],
            'service_id' => $details['kind'] === GiftCardKind::Service ? $details['service']?->id : null,
            'amount' => $value,
            'balance' => $value,
            'currency' => Service::CURRENCY,
            'purchaser_name' => $details['purchaser_name'],
            'purchaser_email' => $details['purchaser_email'],
            'recipient_name' => $details['recipient_name'],
            'recipient_email' => $details['recipient_email'],
            'message' => $details['message'],
            'expires_on' => $details['expires_on'] === null ? null : Carbon::parse($details['expires_on'])->toDateString(),
        ];
    }

    /**
     * A code no other card has
     */
    protected function newCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 12; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $code = self::formatCode($code);
        } while (GiftCard::query()->where('code', $code)->exists());

        return $code;
    }

    protected function business(): string
    {
        return (string) app(GeneralSettings::class)->site_name;
    }
}

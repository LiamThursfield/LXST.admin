<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Bookings\PaymentStatus;
use App\Enums\Bookings\SavedCardStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Bookings\BookingPayments;
use App\Services\Bookings\GiftCards;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Throwable;

/**
 * Stripe's news about connected accounts: a business finishing its setup, payments on its account
 * going through or not, gift cards bought, cards saved for later fees, and refunds. One endpoint for every tenant,
 * found by the account the news is about.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeGateway $stripe): Response
    {
        try {
            $event = $stripe->eventFrom($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (Throwable) {
            return response('Invalid signature', 400);
        }

        $tenant = is_string($event->account) ? Tenant::query()->firstWhere('stripe_account_id', $event->account) : null;

        if ($tenant === null) {
            // Nothing of ours, so nothing to do, but Stripe shouldn't keep trying
            return response('Ignored');
        }

        if ($event->type === 'account.updated') {
            $tenant->update([
                'stripe_charges_enabled' => (bool) ($event->data->object->charges_enabled ?? false),
                'stripe_details_submitted' => (bool) ($event->data->object->details_submitted ?? false),
            ]);

            return response('OK');
        }

        $object = $event->data->object;
        $payments = app(BookingPayments::class);

        if (str_starts_with($event->type, 'checkout.session.') && ($object->mode ?? null) === 'setup') {
            $cardStatus = $this->cardStatusFor($event);

            if ($cardStatus !== null) {
                $tenant->run(fn () => $payments->settleCard(
                    (string) $object->id,
                    $cardStatus,
                    is_string($object->setup_intent ?? null) ? $object->setup_intent : null,
                ));
            }

            return response('OK');
        }

        if (in_array($event->type, ['refund.created', 'refund.updated', 'refund.failed', 'charge.refund.updated'], true)) {
            $tenant->run(fn () => $payments->settleRefund((string) $object->id, $object->status ?? null));

            return response('OK');
        }

        if (in_array($event->type, ['payment_intent.succeeded', 'payment_intent.payment_failed'], true)) {
            $tenant->run(fn () => $payments->settleIntent(
                (string) $object->id,
                $event->type === 'payment_intent.succeeded' ? PaymentStatus::Paid : PaymentStatus::Failed,
                $object->last_payment_error->message ?? null,
            ));

            return response('OK');
        }

        $status = $this->paymentStatusFor($event);

        // A gift card bought online, rather than a payment towards a booking
        if ($status !== null && is_string($object->metadata->gift_card_id ?? null)) {
            $tenant->run(fn () => app(GiftCards::class)->settle(
                (string) $object->id,
                $status,
                is_string($object->payment_intent ?? null) ? $object->payment_intent : null,
            ));

            return response('OK');
        }

        if ($status !== null) {
            $tenant->run(fn () => $payments->settle(
                (string) $object->id,
                $status,
                is_string($object->payment_intent ?? null) ? $object->payment_intent : null,
            ));
        }

        return response('OK');
    }

    /**
     * A card is saved once its setup page is completed
     */
    protected function cardStatusFor(Event $event): ?SavedCardStatus
    {
        return match ($event->type) {
            'checkout.session.completed' => SavedCardStatus::Saved,
            'checkout.session.expired' => SavedCardStatus::Expired,
            default => null,
        };
    }

    /**
     * A checkout paid by card is paid when completed; one paid by bank transfer and the like is paid
     * when Stripe says the money has arrived
     */
    protected function paymentStatusFor(Event $event): ?PaymentStatus
    {
        return match ($event->type) {
            'checkout.session.completed' => ($event->data->object->payment_status ?? null) === 'paid' ? PaymentStatus::Paid : null,
            'checkout.session.async_payment_succeeded' => PaymentStatus::Paid,
            'checkout.session.async_payment_failed' => PaymentStatus::Failed,
            'checkout.session.expired' => PaymentStatus::Expired,
            default => null,
        };
    }
}

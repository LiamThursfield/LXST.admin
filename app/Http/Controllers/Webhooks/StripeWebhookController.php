<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Bookings\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Bookings\BookingPayments;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Event;
use Throwable;

/**
 * Stripe's news about connected accounts: a business finishing its setup, and payments on its account
 * going through or not. One endpoint for every tenant, found by the account the news is about.
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

        $status = $this->paymentStatusFor($event);

        if ($status !== null) {
            $session = $event->data->object;

            $tenant->run(fn () => app(BookingPayments::class)->settle(
                (string) $session->id,
                $status,
                is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
            ));
        }

        return response('OK');
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

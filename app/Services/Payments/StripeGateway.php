<?php

namespace App\Services\Payments;

use Stripe\Event;
use Stripe\Exception\CardException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * The few Stripe calls the platform makes: linking a business's own Standard account through Connect,
 * taking payments on it with any platform fee taken off by Stripe on the way, keeping cards for later
 * fees, and refunds. Everything happens on the business's account, so disputes are theirs, in their
 * own Stripe dashboard.
 */
class StripeGateway
{
    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * A new account the business owns, with its own full Stripe dashboard: what Stripe calls
     * Standard. Returns its ID.
     *
     * @param  array<string, string>  $metadata
     */
    public function createAccount(?string $email, array $metadata): string
    {
        $account = $this->client()->accounts->create(array_filter([
            'email' => $email,
            'metadata' => $metadata,
            'controller' => [
                'stripe_dashboard' => ['type' => 'full'],
                'fees' => ['payer' => 'account'],
                'losses' => ['payments' => 'stripe'],
                'requirement_collection' => 'stripe',
            ],
        ]));

        return $account->id;
    }

    /**
     * Where to send the business to fill in their details with Stripe
     */
    public function onboardingUrl(string $accountId, string $returnUrl, string $refreshUrl): string
    {
        return $this->client()->accountLinks->create([
            'account' => $accountId,
            'type' => 'account_onboarding',
            'return_url' => $returnUrl,
            'refresh_url' => $refreshUrl,
        ])->url;
    }

    /**
     * @return array{charges_enabled: bool, details_submitted: bool}
     */
    public function accountStatus(string $accountId): array
    {
        $account = $this->client()->accounts->retrieve($accountId);

        return [
            'charges_enabled' => (bool) $account->charges_enabled,
            'details_submitted' => (bool) $account->details_submitted,
        ];
    }

    /**
     * A Stripe-hosted page to pay on, on the business's account. With $saveCard, the card is also kept
     * for later fees, which Stripe tells the customer about on the page.
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function createCheckout(string $accountId, int $amount, string $currency, string $description, int $applicationFee, ?string $customerEmail, string $successUrl, string $cancelUrl, array $metadata, bool $saveCard = false): array
    {
        $session = $this->client()->checkout->sessions->create(array_filter([
            'mode' => 'payment',
            'customer_creation' => $saveCard ? 'always' : null,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $amount,
                    'product_data' => ['name' => $description],
                ],
            ]],
            'payment_intent_data' => array_filter([
                'application_fee_amount' => $applicationFee > 0 ? $applicationFee : null,
                'setup_future_usage' => $saveCard ? 'off_session' : null,
                'metadata' => $metadata,
            ]),
            'customer_email' => $customerEmail,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ]), ['stripe_account' => $accountId]);

        return ['id' => $session->id, 'url' => (string) $session->url];
    }

    /**
     * A customer on the business's account, to keep a card against. Returns its ID.
     */
    public function createCustomer(string $accountId, string $name, ?string $email): string
    {
        return $this->client()->customers->create(array_filter([
            'name' => $name,
            'email' => $email,
        ]), ['stripe_account' => $accountId])->id;
    }

    /**
     * A Stripe-hosted page to save a card on, for fees charged later. Cards only, as only cards can
     * be charged without the customer there.
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function createCardSetup(string $accountId, string $customerId, string $currency, string $successUrl, string $cancelUrl, array $metadata): array
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'setup',
            'customer' => $customerId,
            'currency' => strtolower($currency),
            'payment_method_types' => ['card'],
            'setup_intent_data' => ['metadata' => $metadata],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ], ['stripe_account' => $accountId]);

        return ['id' => $session->id, 'url' => (string) $session->url];
    }

    /**
     * The card saved through a setup page, or while paying
     *
     * @return array{customer: string, payment_method: string, brand: string|null, last4: string|null}|null
     */
    public function savedCard(string $accountId, ?string $setupIntentId = null, ?string $paymentIntentId = null): ?array
    {
        $options = ['stripe_account' => $accountId];
        $params = ['expand' => ['payment_method']];

        $intent = match (true) {
            $setupIntentId !== null => $this->client()->setupIntents->retrieve($setupIntentId, $params, $options),
            $paymentIntentId !== null => $this->client()->paymentIntents->retrieve($paymentIntentId, $params, $options),
            default => null,
        };

        $method = $intent?->payment_method;
        $customer = $intent?->customer;

        if (! is_object($method) || $customer === null) {
            return null;
        }

        return [
            'customer' => is_string($customer) ? $customer : $customer->id,
            'payment_method' => $method->id,
            'brand' => $method->card->brand ?? null,
            'last4' => $method->card->last4 ?? null,
        ];
    }

    /**
     * Charges a saved card without the customer there, e.g. a no-show fee
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string|null, status: string, error: string|null} Stripe's status: succeeded, processing, or failed when declined or the bank wants the customer to confirm
     */
    public function chargeSavedCard(string $accountId, string $customerId, string $paymentMethodId, int $amount, string $currency, int $applicationFee, string $description, array $metadata): array
    {
        try {
            $params = [
                'amount' => $amount,
                'currency' => strtolower($currency),
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'off_session' => true,
                'confirm' => true,
                'description' => $description,
                'metadata' => $metadata,
            ];

            if ($applicationFee > 0) {
                $params['application_fee_amount'] = $applicationFee;
            }

            $intent = $this->client()->paymentIntents->create($params, ['stripe_account' => $accountId]);
        } catch (CardException $exception) {
            return ['id' => $exception->getError()?->payment_intent?->id, 'status' => 'failed', 'error' => $exception->getMessage()];
        }

        return ['id' => $intent->id, 'status' => $intent->status === 'succeeded' ? 'succeeded' : ($intent->status === 'processing' ? 'processing' : 'failed'), 'error' => null];
    }

    /**
     * Gives back some or all of a payment, with the platform's share of its fee
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, status: string|null}
     */
    public function refund(string $accountId, string $paymentIntentId, int $amount, array $metadata): array
    {
        $refund = $this->client()->refunds->create([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
            'refund_application_fee' => true,
            'metadata' => $metadata,
        ], ['stripe_account' => $accountId]);

        return ['id' => $refund->id, 'status' => $refund->status];
    }

    /**
     * Checks a webhook came from Stripe
     *
     * @throws SignatureVerificationException
     * @throws \UnexpectedValueException
     */
    public function eventFrom(string $payload, string $signature): Event
    {
        return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));
    }

    protected function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}

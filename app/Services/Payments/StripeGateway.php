<?php

namespace App\Services\Payments;

use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * The few Stripe calls the platform makes: linking a business's own Standard account through Connect,
 * and taking payments on it with any platform fee taken off by Stripe on the way. Payments are made
 * on the business's account, so refunds and disputes are theirs, in their own Stripe dashboard.
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
     * A Stripe-hosted page to pay on, on the business's account
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, url: string}
     */
    public function createCheckout(string $accountId, int $amount, string $currency, string $description, int $applicationFee, ?string $customerEmail, string $successUrl, string $cancelUrl, array $metadata): array
    {
        $session = $this->client()->checkout->sessions->create(array_filter([
            'mode' => 'payment',
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

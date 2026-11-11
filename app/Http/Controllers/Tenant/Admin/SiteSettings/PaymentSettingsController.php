<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\SiteSettings\GiftCardSettingsUpdateRequest;
use App\Models\Settings\BookingsSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Bookings\BookingPayments;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Linking the business's own Stripe account, so it can take payments, and how gift cards are sold
 */
class PaymentSettingsController extends Controller
{
    public function edit(Request $request, StripeGateway $stripe, BookingPayments $payments, BookingsSettings $bookings): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        $tenant = $this->tenant();
        $percent = (float) ($tenant->getAttribute('platform_fee_percent') ?? 0);
        $fixed = (int) ($tenant->getAttribute('platform_fee_fixed') ?? 0);

        return Inertia::render('admin/site-settings/Payments', [
            'platformReady' => $stripe->isConfigured(),
            'connected' => filled($tenant->getAttribute('stripe_account_id')),
            'detailsSubmitted' => (bool) $tenant->getAttribute('stripe_details_submitted'),
            'chargesEnabled' => (bool) $tenant->getAttribute('stripe_charges_enabled'),
            'fee' => $percent > 0 || $fixed > 0 ? ['percent' => $percent, 'fixed' => $fixed > 0 ? $payments->format($fixed) : null] : null,
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
            'giftCards' => [
                'gift_card_months' => $bookings->gift_card_months,
                'gift_cards_online' => $bookings->gift_cards_online,
                'shop_url' => route('bookings.gift-cards.shop'),
            ],
        ]);
    }

    /**
     * How long gift cards last, and whether they're sold on the public page
     */
    public function giftCards(GiftCardSettingsUpdateRequest $request, BookingsSettings $bookings): RedirectResponse
    {
        $months = $request->validated('gift_card_months');

        $bookings->gift_card_months = $months === null ? null : (int) $months;
        $bookings->gift_cards_online = $request->boolean('gift_cards_online');
        $bookings->save();

        return redirect()->route('admin.site-settings.payments.edit')->with('success', __('site_settings.payments.gift_cards_updated'));
    }

    /**
     * Sends the business to Stripe to set up, or finish setting up, their account
     */
    public function connect(Request $request, StripeGateway $stripe): SymfonyResponse
    {
        Gate::authorize(SettingsPermission::ManageSettings);

        $tenant = $this->tenant();

        if (! $stripe->isConfigured()) {
            return back()->with('error', __('site_settings.payments.platform_not_ready'));
        }

        try {
            if (blank($tenant->getAttribute('stripe_account_id'))) {
                /** @var User|null $user */
                $user = $request->user();
                $tenant->update(['stripe_account_id' => $stripe->createAccount($user?->email, ['tenant_id' => (string) $tenant->getTenantKey()])]);
            }

            $url = $stripe->onboardingUrl(
                (string) $tenant->getAttribute('stripe_account_id'),
                route('admin.site-settings.payments.return'),
                route('admin.site-settings.payments.refresh'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('site_settings.payments.connect_failed'));
        }

        return Inertia::location($url);
    }

    /**
     * A new setup link for an account already made, when Stripe says the last one has run out
     */
    public function refresh(StripeGateway $stripe): SymfonyResponse
    {
        Gate::authorize(SettingsPermission::ManageSettings);

        $accountId = $this->tenant()->getAttribute('stripe_account_id');

        if (! is_string($accountId) || $accountId === '' || ! $stripe->isConfigured()) {
            return redirect()->route('admin.site-settings.payments.edit');
        }

        try {
            $url = $stripe->onboardingUrl($accountId, route('admin.site-settings.payments.return'), route('admin.site-settings.payments.refresh'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.site-settings.payments.edit')->with('error', __('site_settings.payments.connect_failed'));
        }

        return Inertia::location($url);
    }

    /**
     * Where Stripe sends the business back to: their account's latest state is fetched, as the
     * webhook saying so may not have arrived yet
     */
    public function return(StripeGateway $stripe): RedirectResponse
    {
        Gate::authorize(SettingsPermission::ManageSettings);

        $tenant = $this->tenant();
        $accountId = $tenant->getAttribute('stripe_account_id');

        if (is_string($accountId) && $accountId !== '') {
            try {
                $status = $stripe->accountStatus($accountId);
                $tenant->update([
                    'stripe_charges_enabled' => $status['charges_enabled'],
                    'stripe_details_submitted' => $status['details_submitted'],
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('admin.site-settings.payments.edit');
    }

    /**
     * Unlinks the account here. It stays the business's own in Stripe.
     */
    public function disconnect(): RedirectResponse
    {
        Gate::authorize(SettingsPermission::ManageSettings);

        $this->tenant()->update(['stripe_account_id' => null, 'stripe_charges_enabled' => false, 'stripe_details_submitted' => false]);

        return redirect()->route('admin.site-settings.payments.edit')->with('success', __('site_settings.payments.disconnected'));
    }

    protected function tenant(): Tenant
    {
        $tenant = tenant();

        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}

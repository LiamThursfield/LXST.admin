<?php

namespace App\Http\Controllers\Tenant\Public\Bookings;

use App\Enums\Bookings\GiftCardKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Bookings\GiftCardController;
use App\Http\Requests\Tenant\Public\Bookings\GiftCardPurchaseRequest;
use App\Models\Settings\BookingsSettings;
use App\Models\Settings\GeneralSettings;
use App\Models\Tenant;
use App\Services\Bookings\BookingPayments;
use App\Services\Bookings\GiftCards;
use App\Services\Payments\StripeGateway;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The public page to buy a gift card or voucher, paid on Stripe. Only there when the business has
 * switched it on and can take payments.
 */
class GiftCardShopController extends Controller
{
    public function show(GeneralSettings $general, BookingPayments $payments): Response
    {
        $this->ensureOnSale();

        $services = GiftCardController::servicesWithPrices(onlineOnly: true);

        return Inertia::render('public/bookings/GiftCardShop', [
            'business' => $general->site_name,
            'kinds' => $services === [] ? [GiftCardKind::Amount->toSelectItem()] : GiftCardKind::asSelectItems(),
            'services' => $services,
            'minimum' => number_format(GiftCards::ONLINE_MINIMUM / 100, 2, '.', ''),
            'maximum' => number_format(GiftCards::ONLINE_MAXIMUM / 100, 2, '.', ''),
            'minimumLabel' => $payments->format(GiftCards::ONLINE_MINIMUM),
            'maximumLabel' => $payments->format(GiftCards::ONLINE_MAXIMUM),
        ]);
    }

    /**
     * Sets the card up and sends the customer to Stripe to pay for it
     */
    public function store(GiftCardPurchaseRequest $request, GiftCards $giftCards): SymfonyResponse
    {
        $this->ensureOnSale();

        $card = $giftCards->buy($request->details());

        return Inertia::location((string) $card->checkout_url);
    }

    protected function ensureOnSale(): void
    {
        $tenant = tenant();

        abort_unless(
            app(BookingsSettings::class)->gift_cards_online && $tenant instanceof Tenant && $tenant->canTakePayments() && app(StripeGateway::class)->isConfigured(),
            404,
        );
    }
}

<?php

namespace App\Http\Controllers\Tenant\Public\Bookings;

use App\Enums\Bookings\GiftCardStatus;
use App\Http\Controllers\Controller;
use App\Models\Bookings\GiftCard;
use App\Models\Settings\GeneralSettings;
use App\Services\Bookings\BookingPayments;
use App\Services\Bookings\GiftCards;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A gift card's own page: it takes the buyer to pay while it's waiting, and shows the card, its code
 * and what's left once it's paid for
 */
class GiftCardController extends Controller
{
    public function show(string $publicId, GiftCards $giftCards, BookingPayments $payments, GeneralSettings $general): Response|RedirectResponse|SymfonyResponse
    {
        $card = GiftCard::query()->with('service')->where('public_id', $publicId)->firstOrFail();

        if ($card->isPayable() && ! request()->boolean('back')) {
            return Inertia::location((string) $card->checkout_url);
        }

        $status = $card->currentStatus();
        $ready = in_array($status, [GiftCardStatus::Active, GiftCardStatus::Used], true) || ($status === GiftCardStatus::Expired && $card->paid_at !== null);

        return Inertia::render('public/bookings/GiftCard', [
            'business' => $general->site_name,
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'value' => $giftCards->valueLabel($card),
            'isService' => $card->kind->value === 'service',
            'price' => $payments->format($card->price_paid, $card->currency),
            // The code only once it's paid for
            'code' => $ready ? $card->code : null,
            'balance' => $ready ? $giftCards->balanceLabel($card) : null,
            'recipientName' => $card->recipient_name,
            'purchaserName' => $card->purchaser_name,
            'message' => $card->message,
            'expiresOn' => $card->expires_on?->format('j F Y'),
            'payUrl' => $card->isPayable() ? (string) $card->checkout_url : null,
        ]);
    }
}

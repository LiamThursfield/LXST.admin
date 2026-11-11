<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\GiftCardRedeemRequest;
use App\Models\Bookings\Booking;
use App\Services\Bookings\GiftCards;
use Illuminate\Http\RedirectResponse;

/**
 * Paying towards a booking with a gift card or voucher, by its code
 */
class BookingGiftCardController extends Controller
{
    public function store(GiftCardRedeemRequest $request, Booking $booking, GiftCards $giftCards): RedirectResponse
    {
        $giftCards->redeem($booking, $request->string('code')->toString(), $request->amountInMinorUnits(), $request->user());

        return redirect()->back()->with('success', __('bookings.gift_cards.flash.redeemed'));
    }
}

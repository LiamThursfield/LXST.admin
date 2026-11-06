<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\FeeChargeRequest;
use App\Models\Bookings\Booking;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A card kept for a booking's no-show or late cancellation fee: asking the customer for one, and
 * charging the fee to it
 */
class BookingCardController extends Controller
{
    /**
     * Sends the customer a link to save a card
     */
    public function store(Request $request, Booking $booking, BookingPayments $payments): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $payments->requestCard($booking, $request->user());

        return redirect()->back()->with('success', __('bookings.payments.flash.card_requested'));
    }

    /**
     * Charges the fee due to the saved card
     */
    public function charge(FeeChargeRequest $request, Booking $booking, BookingPayments $payments): RedirectResponse
    {
        $payments->chargeFee($booking, $request->amountInMinorUnits(), $request->user());

        return redirect()->back()->with('success', __('bookings.payments.flash.fee_charged'));
    }
}

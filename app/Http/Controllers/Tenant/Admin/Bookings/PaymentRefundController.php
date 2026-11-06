<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\RefundRequest;
use App\Models\Bookings\Payment;
use App\Services\Bookings\BookingPayments;
use Illuminate\Http\RedirectResponse;

/**
 * Giving back some or all of a payment
 */
class PaymentRefundController extends Controller
{
    public function store(RefundRequest $request, Payment $payment, BookingPayments $payments): RedirectResponse
    {
        $payments->refund($payment, $request->amountInMinorUnits(), $request->validated('reason'), $request->user());

        return redirect()->back()->with('success', __('bookings.payments.flash.refunded'));
    }
}

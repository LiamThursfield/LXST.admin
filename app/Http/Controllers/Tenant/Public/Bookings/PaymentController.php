<?php

namespace App\Http\Controllers\Tenant\Public\Bookings;

use App\Enums\Bookings\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Bookings\Payment;
use App\Models\Settings\GeneralSettings;
use App\Services\Bookings\BookingPayments;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The link sent to a customer: it takes them to pay while it can, and says how it went afterwards
 */
class PaymentController extends Controller
{
    public function show(string $publicId, BookingPayments $payments, GeneralSettings $general): Response|RedirectResponse|SymfonyResponse
    {
        $payment = Payment::query()->with('booking')->where('public_id', $publicId)->firstOrFail();

        if ($payment->isPayable() && ! request()->boolean('back')) {
            return Inertia::location((string) $payment->checkout_url);
        }

        return Inertia::render('public/bookings/Payment', [
            'business' => $general->site_name,
            'status' => $payment->status->value,
            'expired' => $payment->status === PaymentStatus::Expired || ($payment->status === PaymentStatus::Pending && ! $payment->isPayable()),
            'amount' => $payments->format($payment->amount),
            'service' => $payment->booking->service_name,
            'payUrl' => $payment->isPayable() ? (string) $payment->checkout_url : null,
        ]);
    }
}

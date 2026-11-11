<?php

namespace App\Http\Controllers\Tenant\Public\Bookings;

use App\Enums\Bookings\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Bookings\Invoice;
use App\Models\Tenant;
use App\Services\Bookings\BookingDocuments;
use App\Services\Bookings\BookingPayments;
use App\Services\Payments\StripeGateway;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The link sent to a customer with an invoice: it shows what's paid and owed, prints as a PDF, and
 * takes them to Stripe to pay what's owed
 */
class InvoiceController extends Controller
{
    public function show(string $publicId, BookingDocuments $documents, BookingPayments $payments, StripeGateway $stripe): Response
    {
        $invoice = Invoice::query()->with('booking')->where('public_id', $publicId)->firstOrFail();
        $owed = $documents->owed($invoice);
        $status = $invoice->currentStatus($owed);
        $tenant = tenant();

        return Inertia::render('public/bookings/Invoice', [
            'publicId' => $invoice->public_id,
            'document' => [
                ...$documents->present($invoice),
                'paid' => $payments->format($invoice->status === InvoiceStatus::Void ? 0 : min($invoice->total, $payments->paid($invoice->booking)), $invoice->currency),
                'owed' => $payments->format($owed, $invoice->currency),
            ],
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'issuedOn' => $invoice->issued_on->format('j F Y'),
            'dueOn' => $invoice->due_on?->format('j F Y'),
            'canPay' => $owed >= BookingPayments::MINIMUM && $tenant instanceof Tenant && $tenant->canTakePayments() && $stripe->isConfigured(),
        ]);
    }

    /**
     * Sends the customer to Stripe to pay what's owed
     */
    public function pay(string $publicId, BookingDocuments $documents): SymfonyResponse
    {
        $invoice = Invoice::query()->with('booking')->where('public_id', $publicId)->firstOrFail();

        $payment = $documents->payLink($invoice);

        return Inertia::location((string) $payment->checkout_url);
    }
}

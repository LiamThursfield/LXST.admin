<?php

namespace App\Http\Controllers\Tenant\Public\Bookings;

use App\Enums\Bookings\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Bookings\Quote;
use App\Services\Bookings\BookingDocuments;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The link sent to a customer with a quote, to accept or decline it
 */
class QuoteController extends Controller
{
    public function show(string $publicId, BookingDocuments $documents): Response
    {
        $quote = $this->find($publicId);
        $status = $quote->currentStatus();

        return Inertia::render('public/bookings/Quote', [
            'publicId' => $quote->public_id,
            'document' => $documents->present($quote),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'validUntil' => $quote->valid_until?->format('j F Y'),
            'open' => $quote->isOpen(),
        ]);
    }

    public function accept(string $publicId, BookingDocuments $documents): RedirectResponse
    {
        $documents->acceptQuote($this->find($publicId));

        return redirect()->route('bookings.quotes.show', $publicId);
    }

    public function decline(string $publicId, BookingDocuments $documents): RedirectResponse
    {
        $documents->declineQuote($this->find($publicId));

        return redirect()->route('bookings.quotes.show', $publicId);
    }

    /**
     * A quote that's been sent; one still being written isn't the customer's to see
     */
    protected function find(string $publicId): Quote
    {
        return Quote::query()->where('public_id', $publicId)->where('status', '!=', QuoteStatus::Draft)->firstOrFail();
    }
}

<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\InvoiceStatus;
use App\Enums\Bookings\PaymentStatus;
use App\Enums\Bookings\QuoteStatus;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Invoice;
use App\Models\Bookings\LineItem;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Quote;
use App\Models\Bookings\Service;
use App\Models\Settings\BookingsSettings;
use App\Models\Settings\GeneralSettings;
use App\Models\User;
use App\Services\Industry\Terminology;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quotes and invoices for bookings: their lines and totals with any tax, numbering them in order,
 * sending them to the customer as links, accepting quotes, and paying what an invoice says is owed.
 * A booking has one invoice at a time, and its price follows an accepted quote or an invoice, so
 * what's owed is always the booking's own outstanding amount.
 */
class BookingDocuments
{
    public function __construct(
        protected BookingPayments $payments,
        protected BookingMessenger $messenger,
        protected Terminology $terminology,
        protected BookingsSettings $settings,
    ) {}

    /**
     * The lines with their totals, and what they add up to with any tax
     *
     * @param  list<array{description: string, quantity: string|float|int, unit_price: int}>  $lines
     * @return array{lines: list<array{description: string, quantity: string, unit_price: int, total: int, order: int}>, subtotal: int, tax_rate: string|null, tax: int, total: int}
     */
    public function totals(array $lines, ?float $taxRate): array
    {
        $priced = [];

        foreach ($lines as $order => $line) {
            $quantity = round((float) $line['quantity'], 2);

            $priced[] = [
                'description' => trim($line['description']),
                'quantity' => number_format($quantity, 2, '.', ''),
                'unit_price' => $line['unit_price'],
                'total' => (int) round($quantity * $line['unit_price']),
                'order' => $order,
            ];
        }

        $subtotal = array_sum(array_column($priced, 'total'));
        $tax = $taxRate === null || $taxRate <= 0 ? 0 : (int) round($subtotal * $taxRate / 100);

        return [
            'lines' => $priced,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate === null || $taxRate <= 0 ? null : number_format($taxRate, 2, '.', ''),
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ];
    }

    /**
     * A new quote, not sent yet
     *
     * @param  list<array{description: string, quantity: string|float|int, unit_price: int}>  $lines
     *
     * @throws ValidationException When the lines add up to less than nothing
     */
    public function createQuote(Booking $booking, array $lines, ?string $notes, ?CarbonInterface $validUntil, ?User $by = null): Quote
    {
        return DB::transaction(function () use ($booking, $lines, $notes, $validUntil, $by) {
            $totals = $this->checkedTotals($lines);

            $quote = $booking->quotes()->create([
                ...$this->amounts($totals),
                'number' => $this->nextNumber(Quote::class, 'QUO'),
                'status' => QuoteStatus::Draft,
                'currency' => Service::CURRENCY,
                'notes' => $notes,
                'valid_until' => $validUntil?->toDateString(),
                'created_by_id' => $by?->id,
            ]);

            $this->saveLines($quote, $totals['lines']);

            return $quote;
        });
    }

    /**
     * Changes a quote the customer hasn't answered yet, with today's tax rate
     *
     * @param  list<array{description: string, quantity: string|float|int, unit_price: int}>  $lines
     *
     * @throws ValidationException When it's been answered, or the lines add up to less than nothing
     */
    public function updateQuote(Quote $quote, array $lines, ?string $notes, ?CarbonInterface $validUntil): Quote
    {
        if (! $quote->isEditable()) {
            throw ValidationException::withMessages(['lines' => __('bookings.documents.validation.answered')]);
        }

        return DB::transaction(function () use ($quote, $lines, $notes, $validUntil) {
            $totals = $this->checkedTotals($lines);

            $quote->update([
                ...$this->amounts($totals),
                'notes' => $notes,
                'valid_until' => $validUntil?->toDateString(),
            ]);

            $quote->lines()->delete();
            $this->saveLines($quote, $totals['lines']);

            return $quote;
        });
    }

    /**
     * Sends the customer the link to the quote, to accept or decline
     *
     * @throws ValidationException When it's been answered
     */
    public function sendQuote(Quote $quote): Quote
    {
        if (! $quote->isEditable()) {
            throw ValidationException::withMessages(['quote' => __('bookings.documents.validation.answered')]);
        }

        $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);

        $this->messenger->sendCustom($quote->booking, $this->terminology->translate('bookings.documents.quote_message', [
            'number' => $quote->number,
            'total' => $this->payments->format($quote->total, $quote->currency),
            'link' => route('bookings.quotes.show', $quote->public_id),
        ]));

        return $quote;
    }

    /**
     * Accepts a quote, which sets the booking's price to its total. The customer can only accept
     * one that's open; staff can also accept one that's past its date or not sent, e.g. agreed by phone.
     *
     * @throws ValidationException When it can't be accepted
     */
    public function acceptQuote(Quote $quote, bool $byStaff = false): Quote
    {
        if (! ($byStaff ? $quote->isEditable() : $quote->isOpen())) {
            throw ValidationException::withMessages(['quote' => __('bookings.documents.validation.not_open')]);
        }

        DB::transaction(function () use ($quote) {
            $quote->update(['status' => QuoteStatus::Accepted, 'accepted_at' => now()]);
            $quote->booking->update(['price' => $quote->total]);
        });

        return $quote;
    }

    /**
     * @throws ValidationException When it can't be declined
     */
    public function declineQuote(Quote $quote, bool $byStaff = false): Quote
    {
        if (! ($byStaff ? $quote->isEditable() : $quote->isOpen())) {
            throw ValidationException::withMessages(['quote' => __('bookings.documents.validation.not_open')]);
        }

        $quote->update(['status' => QuoteStatus::Declined, 'declined_at' => now()]);

        return $quote;
    }

    /**
     * The lines a new invoice starts with: the accepted quote's, or the booking's price
     *
     * @return array{quote_id: int|null, lines: list<array{description: string, quantity: string, unit_price: int}>}
     */
    public function suggestedInvoiceLines(Booking $booking): array
    {
        $quote = $booking->quotes()->where('status', QuoteStatus::Accepted)->latest('accepted_at')->latest('id')->with('lines')->first();

        if ($quote !== null) {
            return [
                'quote_id' => $quote->id,
                // Before tax, as tax is added on top again
                'lines' => array_values($quote->lines->map(fn (LineItem $line) => [
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                ])->all()),
            ];
        }

        $rate = $this->taxRate();
        $price = (int) $booking->price;

        return [
            'quote_id' => null,
            'lines' => $booking->price === null ? [] : [[
                'description' => $booking->service_name,
                'quantity' => '1.00',
                // The price already includes any tax, so the line is what's left before it
                'unit_price' => $rate === null ? $price : (int) round($price / (1 + $rate / 100)),
            ]],
        ];
    }

    /**
     * Issues an invoice, numbered next, and sets the booking's price to its total
     *
     * @param  list<array{description: string, quantity: string|float|int, unit_price: int}>  $lines
     *
     * @throws ValidationException When the booking already has one, or the lines add up to nothing
     */
    public function createInvoice(Booking $booking, array $lines, ?string $notes, ?CarbonInterface $dueOn, ?int $quoteId = null, ?User $by = null): Invoice
    {
        return DB::transaction(function () use ($booking, $lines, $notes, $dueOn, $quoteId, $by) {
            if ($this->currentInvoice($booking) !== null) {
                throw ValidationException::withMessages(['lines' => __('bookings.documents.validation.has_invoice')]);
            }

            $totals = $this->checkedTotals($lines, minimum: 1);

            $invoice = $booking->invoices()->create([
                ...$this->amounts($totals),
                'number' => $this->nextNumber(Invoice::class, 'INV'),
                'quote_id' => $quoteId !== null && $booking->quotes()->whereKey($quoteId)->exists() ? $quoteId : null,
                'status' => InvoiceStatus::Issued,
                'currency' => Service::CURRENCY,
                'notes' => $notes,
                'issued_on' => today()->toDateString(),
                'due_on' => $dueOn?->toDateString(),
                'created_by_id' => $by?->id,
            ]);

            $this->saveLines($invoice, $totals['lines']);

            $booking->update(['price' => $invoice->total]);

            return $invoice;
        });
    }

    /**
     * The booking's invoice that hasn't been voided
     */
    public function currentInvoice(Booking $booking): ?Invoice
    {
        return $booking->invoices()->where('status', '!=', InvoiceStatus::Void)->latest('id')->first();
    }

    /**
     * What's still to pay on an invoice
     */
    public function owed(Invoice $invoice): int
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return 0;
        }

        return max(0, $invoice->total - $this->payments->paid($invoice->booking));
    }

    /**
     * Sends the customer the link to the invoice, where they can pay it
     *
     * @throws ValidationException When it's been voided
     */
    public function sendInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Void) {
            throw ValidationException::withMessages(['invoice' => __('bookings.documents.validation.void')]);
        }

        $invoice->update(['status' => InvoiceStatus::Sent, 'sent_at' => now()]);

        $this->messenger->sendCustom($invoice->booking, $this->terminology->translate('bookings.documents.invoice_message', [
            'number' => $invoice->number,
            'total' => $this->payments->format($this->owed($invoice), $invoice->currency),
            'link' => route('bookings.invoices.show', $invoice->public_id),
        ]));

        return $invoice;
    }

    /**
     * Cancels an invoice, keeping it and its number, so another can be issued
     */
    public function voidInvoice(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => InvoiceStatus::Void, 'voided_at' => now()]);

        return $invoice;
    }

    /**
     * A Stripe link for what's owed on an invoice, reusing one already made for the same amount
     *
     * @throws ValidationException When nothing's owed, or payments can't be taken
     */
    public function payLink(Invoice $invoice): Payment
    {
        $owed = $this->owed($invoice);

        if ($owed === 0) {
            throw ValidationException::withMessages(['invoice' => __('bookings.documents.validation.nothing_owed')]);
        }

        $open = $invoice->booking->payments()
            ->where('status', PaymentStatus::Pending)
            ->where('amount', $owed)
            ->whereNotNull('checkout_url')
            ->latest('id')
            ->get()
            ->first(fn (Payment $payment) => $payment->isPayable());

        return $open ?? $this->payments->request($invoice->booking, $owed, notify: false);
    }

    /**
     * Everything a quote or invoice shows, for the admin and the customer's page
     *
     * @return array<string, mixed>
     */
    public function present(Quote|Invoice $document): array
    {
        $document->loadMissing(['lines', 'booking']);
        $booking = $document->booking;
        $format = fn (int $amount) => $this->payments->format($amount, $document->currency);

        return [
            'number' => $document->number,
            'business' => app(GeneralSettings::class)->site_name,
            'business_details' => $this->settings->business_details,
            'tax_number' => $this->settings->tax_number === null ? null : "{$this->settings->tax_label} {$this->settings->tax_number}",
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'service_name' => $booking->service_name,
            'when' => $booking->starts_at?->setTimezone($booking->timezone)->format('D j M Y, H:i'),
            'lines' => $document->lines->map(fn (LineItem $line) => [
                'description' => $line->description,
                // 2.00 reads as 2, 2.50 as 2.5
                'quantity' => rtrim(rtrim($line->quantity, '0'), '.'),
                'unit_price' => $format($line->unit_price),
                'total' => $format($line->total),
            ])->values()->all(),
            'subtotal' => $format($document->subtotal),
            'tax_label' => $document->tax_rate === null ? null : $this->settings->tax_label.' ('.rtrim(rtrim($document->tax_rate, '0'), '.').'%)',
            'tax' => $document->tax_rate === null ? null : $format($document->tax),
            'total' => $format($document->total),
            'notes' => $document->notes,
        ];
    }

    /**
     * The business's tax rate now, or null when it doesn't add tax
     */
    public function taxRate(): ?float
    {
        $rate = $this->settings->tax_rate;

        return $rate === null || $rate <= 0 ? null : $rate;
    }

    /**
     * The last day a quote written today can be accepted, from the business's setting
     */
    public function defaultValidUntil(): ?CarbonInterface
    {
        $days = $this->settings->quote_valid_days;

        return $days === null || $days < 1 ? null : today()->addDays($days);
    }

    /**
     * When an invoice issued today is due, from the business's setting
     */
    public function defaultDueOn(): ?CarbonInterface
    {
        $days = $this->settings->invoice_due_days;

        return $days === null || $days < 0 ? null : today()->addDays($days);
    }

    /**
     * @param  list<array{description: string, quantity: string|float|int, unit_price: int}>  $lines
     * @return array{lines: list<array{description: string, quantity: string, unit_price: int, total: int, order: int}>, subtotal: int, tax_rate: string|null, tax: int, total: int}
     *
     * @throws ValidationException When they add up to less than allowed
     */
    protected function checkedTotals(array $lines, int $minimum = 0): array
    {
        $totals = $this->totals($lines, $this->taxRate());

        if ($totals['subtotal'] < $minimum) {
            throw ValidationException::withMessages(['lines' => __('bookings.documents.validation.total_too_small')]);
        }

        return $totals;
    }

    /**
     * @param  array{subtotal: int, tax_rate: string|null, tax: int, total: int}  $totals
     * @return array{subtotal: int, tax_rate: string|null, tax: int, total: int}
     */
    protected function amounts(array $totals): array
    {
        return [
            'subtotal' => $totals['subtotal'],
            'tax_rate' => $totals['tax_rate'],
            'tax' => $totals['tax'],
            'total' => $totals['total'],
        ];
    }

    /**
     * @param  list<array{description: string, quantity: string, unit_price: int, total: int, order: int}>  $lines
     */
    protected function saveLines(Quote|Invoice $document, array $lines): void
    {
        $document->lines()->createMany($lines);
    }

    /**
     * The next number in order, e.g. INV-0042. Locked, so two issued at once can't share one.
     *
     * @param  class-string<Model>  $model
     */
    protected function nextNumber(string $model, string $prefix): string
    {
        $last = $model::query()->where('number', 'like', "{$prefix}-%")->lockForUpdate()->orderByDesc('id')->value('number');
        $next = is_string($last) ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * A date from a form, or null
     */
    public static function date(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}

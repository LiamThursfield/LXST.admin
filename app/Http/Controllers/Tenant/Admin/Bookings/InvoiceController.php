<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\DocumentIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\InvoiceStoreRequest;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Invoice;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingDocuments;
use App\Services\Bookings\BookingPayments;
use App\Services\Industry\Terminology;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Invoices for bookings: issuing them, sending them to the customer, and voiding them
 */
class InvoiceController extends Controller
{
    public function index(DocumentIndexRequest $request, BookingDocuments $documents, BookingPayments $payments): Response
    {
        $list = QueryBuilder::for(Invoice::query()->with('booking'), $request)
            ->allowedFilters(
                // Paid and overdue depend on what's been paid, so they're filtered after loading
                AllowedFilter::callback('status', function (Builder $query, string $value) {
                    match (InvoiceStatus::tryFrom($value)) {
                        InvoiceStatus::Void => $query->where('status', InvoiceStatus::Void),
                        InvoiceStatus::Overdue => $query->where('status', '!=', InvoiceStatus::Void)->where('due_on', '<', today()->toDateString()),
                        null => null,
                        default => $query->where('status', $value),
                    };
                }),
                AllowedFilter::callback('search', fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query
                    ->where('number', 'like', "%{$value}%")
                    ->orWhereHas('booking', fn (Builder $query) => $query->where('customer_name', 'like', "%{$value}%")))),
            )
            ->defaultSort('-id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        $terminology = app(Terminology::class);

        return Inertia::render('admin/bookings/invoice/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');

                return $validated == null ? null : collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'number', 'header' => __('bookings.documents.columns.number')],
                ['accessorKey' => 'customer_name', 'header' => $terminology->translate('bookings.documents.columns.customer')],
                ['accessorKey' => 'service_name', 'header' => $terminology->translate('bookings.documents.columns.service')],
                ['accessorKey' => 'total', 'header' => __('bookings.documents.columns.total')],
                ['accessorKey' => 'owed', 'header' => __('bookings.documents.columns.owed')],
                ['accessorKey' => 'date', 'header' => __('bookings.documents.columns.due_on')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.documents.columns.status')],
            ],
            'documents' => [
                'data' => $list->getCollection()->map(function (Invoice $invoice) use ($documents, $payments) {
                    $owed = $documents->owed($invoice);
                    $status = $invoice->currentStatus($owed);

                    return [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'customer_name' => $invoice->booking->customer_name,
                        'service_name' => $invoice->booking->service_name,
                        'total' => $payments->format($invoice->total, $invoice->currency),
                        'owed' => $payments->format($owed, $invoice->currency),
                        'date' => $invoice->due_on?->format('j M Y'),
                        'status' => $status->value,
                        'status_label' => $status->label(),
                        'show_url' => route('admin.bookings.invoices.show', $invoice),
                    ];
                })->values(),
                'meta' => [
                    'current_page' => $list->currentPage(),
                    'last_page' => $list->lastPage(),
                    'total' => $list->total(),
                    'per_page' => $list->perPage(),
                ],
            ],
            'statuses' => array_values(array_filter(InvoiceStatus::asSelectItems(), fn (array $item) => $item['value'] !== InvoiceStatus::Paid->value)),
        ]);
    }

    public function create(Booking $booking, BookingDocuments $documents): Response|RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $current = $documents->currentInvoice($booking);

        if ($current !== null) {
            return redirect()->route('admin.bookings.invoices.show', $current)->with('error', __('bookings.documents.validation.has_invoice'));
        }

        $suggested = $documents->suggestedInvoiceLines($booking);

        return Inertia::render('admin/bookings/invoice/Form', [
            ...QuoteController::formProps($booking, $documents),
            'quoteId' => $suggested['quote_id'],
            'lines' => QuoteController::formLines($suggested['lines']),
            'date' => $documents->defaultDueOn()?->toDateString(),
        ]);
    }

    public function store(InvoiceStoreRequest $request, Booking $booking, BookingDocuments $documents): RedirectResponse
    {
        $quoteId = $request->validated('quote_id');

        $invoice = $documents->createInvoice(
            $booking,
            $request->lines(),
            $request->validated('notes'),
            BookingDocuments::date($request->validated('due_on')),
            $quoteId === null ? null : (int) $quoteId,
            $request->user(),
        );

        if ($request->boolean('send')) {
            $documents->sendInvoice($invoice);
        }

        return redirect()->route('admin.bookings.invoices.show', $invoice)
            ->with('success', __($request->boolean('send') ? 'bookings.documents.flash.invoice_sent' : 'bookings.documents.flash.invoice_issued'));
    }

    public function show(Request $request, Invoice $invoice, BookingDocuments $documents, BookingPayments $payments): Response
    {
        Gate::authorize(BookingsPermission::ViewPayments);

        $owed = $documents->owed($invoice);
        $status = $invoice->currentStatus($owed);

        return Inertia::render('admin/bookings/invoice/Show', [
            'id' => $invoice->id,
            'document' => [
                ...$documents->present($invoice),
                'paid' => $payments->format($invoice->status === InvoiceStatus::Void ? 0 : min($invoice->total, $payments->paid($invoice->booking)), $invoice->currency),
                'owed' => $payments->format($owed, $invoice->currency),
            ],
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'issuedOn' => $invoice->issued_on->format('j M Y'),
            'dueOn' => $invoice->due_on?->format('j M Y'),
            'sentAt' => $invoice->sent_at?->format('D j M Y, H:i'),
            'quote' => $invoice->quote === null ? null : ['number' => $invoice->quote->number, 'url' => route('admin.bookings.quotes.show', $invoice->quote)],
            'link' => route('bookings.invoices.show', $invoice->public_id),
            'bookingUrl' => route('admin.bookings.bookings.show', $invoice->booking_id),
            'canManage' => ($request->user()?->can(BookingsPermission::ManagePayments) ?? false) && $invoice->status !== InvoiceStatus::Void,
        ]);
    }

    /**
     * Sends the customer the link, again if it's been sent before
     */
    public function send(Invoice $invoice, BookingDocuments $documents): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $documents->sendInvoice($invoice);

        return redirect()->back()->with('success', __('bookings.documents.flash.invoice_sent'));
    }

    /**
     * Cancels it, keeping its number, so another can be issued
     */
    public function voidInvoice(Invoice $invoice, BookingDocuments $documents): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $documents->voidInvoice($invoice);

        return redirect()->back()->with('success', __('bookings.documents.flash.invoice_voided'));
    }
}

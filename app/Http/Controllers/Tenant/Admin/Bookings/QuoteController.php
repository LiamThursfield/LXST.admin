<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\DocumentIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\QuoteRequest;
use App\Models\Bookings\Booking;
use App\Models\Bookings\LineItem;
use App\Models\Bookings\Quote;
use App\Models\Bookings\Service;
use App\Models\Settings\BookingsSettings;
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
 * Quotes for bookings: writing them, sending them to the customer, and recording their answer
 */
class QuoteController extends Controller
{
    public function index(DocumentIndexRequest $request, BookingPayments $payments): Response
    {
        $list = QueryBuilder::for(Quote::query()->with('booking'), $request)
            ->allowedFilters(
                AllowedFilter::callback('status', function (Builder $query, string $value) {
                    $today = today()->toDateString();

                    match (QuoteStatus::tryFrom($value)) {
                        QuoteStatus::Expired => $query->where('status', QuoteStatus::Sent)->where('valid_until', '<', $today),
                        QuoteStatus::Sent => $query->where('status', QuoteStatus::Sent)->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today)),
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

        return Inertia::render('admin/bookings/quote/Index', [
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
                ['accessorKey' => 'date', 'header' => __('bookings.documents.columns.valid_until')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.documents.columns.status')],
            ],
            'documents' => [
                'data' => $list->getCollection()->map(fn (Quote $quote) => [
                    'id' => $quote->id,
                    'number' => $quote->number,
                    'customer_name' => $quote->booking->customer_name,
                    'service_name' => $quote->booking->service_name,
                    'total' => $payments->format($quote->total, $quote->currency),
                    'date' => $quote->valid_until?->format('j M Y'),
                    'status' => $quote->currentStatus()->value,
                    'status_label' => $quote->currentStatus()->label(),
                    'show_url' => route('admin.bookings.quotes.show', $quote),
                ])->values(),
                'meta' => [
                    'current_page' => $list->currentPage(),
                    'last_page' => $list->lastPage(),
                    'total' => $list->total(),
                    'per_page' => $list->perPage(),
                ],
            ],
            'statuses' => QuoteStatus::asSelectItems(),
        ]);
    }

    public function create(Booking $booking, BookingDocuments $documents): Response
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        return Inertia::render('admin/bookings/quote/Form', [
            ...$this->formProps($booking, $documents),
            'quote' => null,
            'lines' => [],
            'notes' => null,
            'date' => $documents->defaultValidUntil()?->toDateString(),
        ]);
    }

    public function store(QuoteRequest $request, Booking $booking, BookingDocuments $documents): RedirectResponse
    {
        $quote = $documents->createQuote($booking, $request->lines(), $request->validated('notes'), BookingDocuments::date($request->validated('valid_until')), $request->user());

        if ($request->boolean('send')) {
            $documents->sendQuote($quote);
        }

        return redirect()->route('admin.bookings.quotes.show', $quote)
            ->with('success', __($request->boolean('send') ? 'bookings.documents.flash.quote_sent' : 'bookings.documents.flash.quote_saved'));
    }

    public function show(Request $request, Quote $quote, BookingDocuments $documents): Response
    {
        Gate::authorize(BookingsPermission::ViewPayments);

        $status = $quote->currentStatus();

        return Inertia::render('admin/bookings/quote/Show', [
            'id' => $quote->id,
            'document' => $documents->present($quote),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'validUntil' => $quote->valid_until?->format('j M Y'),
            'sentAt' => $quote->sent_at?->format('D j M Y, H:i'),
            'answeredAt' => ($quote->accepted_at ?? $quote->declined_at)?->format('D j M Y, H:i'),
            'link' => route('bookings.quotes.show', $quote->public_id),
            'bookingUrl' => route('admin.bookings.bookings.show', $quote->booking_id),
            'canManage' => ($request->user()?->can(BookingsPermission::ManagePayments) ?? false) && $quote->isEditable(),
        ]);
    }

    public function edit(Quote $quote, BookingDocuments $documents): Response|RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        if (! $quote->isEditable()) {
            return redirect()->route('admin.bookings.quotes.show', $quote);
        }

        return Inertia::render('admin/bookings/quote/Form', [
            ...$this->formProps($quote->booking, $documents),
            'quote' => ['id' => $quote->id, 'number' => $quote->number, 'sent' => $quote->status === QuoteStatus::Sent],
            'lines' => self::formLines($quote->lines),
            'notes' => $quote->notes,
            'date' => $quote->valid_until?->toDateString(),
        ]);
    }

    public function update(QuoteRequest $request, Quote $quote, BookingDocuments $documents): RedirectResponse
    {
        $documents->updateQuote($quote, $request->lines(), $request->validated('notes'), BookingDocuments::date($request->validated('valid_until')));

        if ($request->boolean('send')) {
            $documents->sendQuote($quote);
        }

        return redirect()->route('admin.bookings.quotes.show', $quote)
            ->with('success', __($request->boolean('send') ? 'bookings.documents.flash.quote_sent' : 'bookings.documents.flash.quote_saved'));
    }

    /**
     * Sends the customer the link, again if it's been sent before
     */
    public function send(Quote $quote, BookingDocuments $documents): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $documents->sendQuote($quote);

        return redirect()->back()->with('success', __('bookings.documents.flash.quote_sent'));
    }

    /**
     * Records the customer accepting it some other way, e.g. by phone
     */
    public function accept(Quote $quote, BookingDocuments $documents): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $documents->acceptQuote($quote, byStaff: true);

        return redirect()->back()->with('success', __('bookings.documents.flash.quote_accepted'));
    }

    public function decline(Quote $quote, BookingDocuments $documents): RedirectResponse
    {
        Gate::authorize(BookingsPermission::ManagePayments);

        $documents->declineQuote($quote, byStaff: true);

        return redirect()->back()->with('success', __('bookings.documents.flash.quote_declined'));
    }

    /**
     * What the quote and invoice forms show about the booking and the business's tax
     *
     * @return array<string, mixed>
     */
    public static function formProps(Booking $booking, BookingDocuments $documents): array
    {
        $rate = $documents->taxRate();

        return [
            'booking' => [
                'id' => $booking->id,
                'customer_name' => $booking->customer_name,
                'service_name' => $booking->service_name,
                'when' => $booking->starts_at?->setTimezone($booking->timezone)->format('D j M Y, H:i'),
                'url' => route('admin.bookings.bookings.show', $booking),
            ],
            'taxRate' => $rate,
            'taxLabel' => app(BookingsSettings::class)->tax_label,
            'currency' => Service::CURRENCY,
        ];
    }

    /**
     * Lines as the form edits them, with prices in pounds
     *
     * @param  iterable<LineItem|array{description: string, quantity: string, unit_price: int}>  $lines
     * @return list<array{description: string, quantity: string, unit_price: string}>
     */
    public static function formLines(iterable $lines): array
    {
        $form = [];

        foreach ($lines as $line) {
            $line = $line instanceof LineItem ? $line->only(['description', 'quantity', 'unit_price']) : $line;

            $form[] = [
                'description' => (string) $line['description'],
                'quantity' => rtrim(rtrim((string) $line['quantity'], '0'), '.'),
                'unit_price' => number_format(((int) $line['unit_price']) / 100, 2, '.', ''),
            ];
        }

        return $form;
    }
}

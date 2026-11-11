<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\MessageStatus;
use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\ResourceType;
use App\Enums\Bookings\SavedCardStatus;
use App\Enums\Bookings\WaitlistStatus;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Bookings\BookingIndexQuery;
use App\Http\Requests\Tenant\Admin\Bookings\BookingIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingMessageRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingMoveRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingScheduleRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingStatusRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingStoreRequest;
use App\Http\Resources\Tenant\Admin\Bookings\BookingResource;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Payment;
use App\Models\Bookings\Quote;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceOption;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Crm\OrganisationUnit;
use App\Models\Tenant;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingCreator;
use App\Services\Bookings\BookingDocuments;
use App\Services\Bookings\BookingMessenger;
use App\Services\Bookings\BookingPayments;
use App\Services\Industry\Terminology;
use App\Services\Payments\StripeGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(BookingIndexRequest $request, BookingIndexQuery $query): Response
    {
        $terminology = app(Terminology::class);

        return Inertia::render('admin/bookings/booking/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'when', 'header' => __('bookings.bookings.columns.when')],
                ['accessorKey' => 'customer_name', 'header' => $terminology->translate('bookings.bookings.columns.customer')],
                ['accessorKey' => 'service_name', 'header' => $terminology->translate('bookings.bookings.columns.service')],
                ['accessorKey' => 'resource_names', 'header' => __('bookings.bookings.columns.resources')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.bookings.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'bookings' => BookingResource::collection($query->handle($request)),
            'statuses' => BookingStatus::asSelectItems(),
            'canCreate' => $request->user()?->can('create', Booking::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Booking::class);

        $entry = $request->filled('waitlist_entry_id')
            ? WaitlistEntry::query()->where('status', WaitlistStatus::Waiting)->find($request->integer('waitlist_entry_id'))
            : null;

        $hasLocations = tenant()?->hasModule(TenantModule::Crm) ?? false;

        return Inertia::render('admin/bookings/booking/Create', [
            'services' => Service::query()->with(['options', 'locations', 'resources'])->orderBy('name')->get()
                ->map(fn (Service $service) => [
                    'value' => $service->id,
                    'label' => $service->name,
                    'booking_type' => $service->booking_type->value,
                    'hire_unit' => $service->hire_unit?->value,
                    'min_quantity' => $service->min_party_size ?? $service->min_hire_units ?? 1,
                    'max_quantity' => $service->max_party_size ?? $service->max_hire_units,
                    'location_ids' => $service->locations->modelKeys(),
                    'options' => $service->options->map(fn (ServiceOption $option) => [
                        'value' => $option->id,
                        'label' => $option->name,
                        'type' => $option->type->value,
                    ])->values(),
                    'resource_types' => array_map(fn (ResourceType $type) => [
                        ...$type->toSelectItem(),
                        'resources' => $service->resources
                            ->filter(fn (BookableResource $resource) => $resource->type === $type && $resource->is_active)
                            ->map(fn (BookableResource $resource) => ['value' => $resource->id, 'label' => $resource->name])
                            ->values(),
                    ], $service->requiredResourceTypes()),
                ])
                ->values(),
            'hasLocations' => $hasLocations,
            'locations' => $hasLocations ? $this->locationOptions() : [],
            'channels' => MessageChannel::asSelectItems(),
            // Booking someone from the waitlist starts from what they asked for
            'prefill' => $entry === null ? null : [
                'waitlist_entry_id' => $entry->id,
                'service_id' => $entry->service_id,
                'location_id' => $entry->location_id,
                'date' => $entry->wanted_on?->toDateString(),
                'quantity' => $entry->quantity,
                'customer_name' => $entry->customer_name,
                'customer_email' => $entry->customer_email,
                'customer_phone' => $entry->customer_phone,
                'notes' => $entry->notes,
            ],
        ]);
    }

    public function store(BookingStoreRequest $request, BookingCreator $creator): RedirectResponse
    {
        /** @var Service $service */
        $service = $request->service();
        /** @var array{date?: string|null, time?: string|null, walk_in?: bool|null, ignore_rules?: bool|null, channel?: string|null, session_id?: int|null, quantity?: int|null, option_ids?: list<int>, resource_ids?: list<int>, customer_name: string, customer_email?: string|null, customer_phone?: string|null, notes?: string|null} $details */
        $details = $request->safe()->except(['service_id', 'location_id', 'repeat_weeks', 'repeat_times', 'waitlist_entry_id']);
        $terminology = app(Terminology::class);

        // Only what passed validation, as repeating is ignored for a walk-in, a session or an enquiry
        $repeatWeeks = $request->validated('repeat_weeks');

        if (is_numeric($repeatWeeks)) {
            /** @var array{date: string, time: string, ignore_rules?: bool|null, quantity?: int|null, option_ids?: list<int>, resource_ids?: list<int>, customer_name: string, customer_email?: string|null, customer_phone?: string|null, notes?: string|null} $details */
            $series = $creator->createSeries($service, $request->location(), $details, (int) $repeatWeeks, $request->integer('repeat_times'), $request->user());
            $booking = $series['booked'][0];
            $message = $series['skipped'] === []
                ? $terminology->translate('bookings.bookings.flash.series_created', ['count' => count($series['booked'])])
                : $terminology->translate('bookings.bookings.flash.series_partly_created', [
                    'count' => count($series['booked']),
                    'total' => count($series['booked']) + count($series['skipped']),
                    'dates' => implode(', ', array_map(fn (string $date) => CarbonImmutable::parse($date)->format('D j M'), $series['skipped'])),
                ]);
        } else {
            $booking = $creator->create($service, $request->location(), $details, $request->user());
            $message = $terminology->translate('bookings.bookings.flash.created');
        }

        // A walk-in is there in person, so has nothing to be told. A series is told once, about the first.
        if (! $booking->is_walk_in) {
            app(BookingMessenger::class)->send($booking, MessageEvent::Booked);
        }

        if ($request->filled('waitlist_entry_id')) {
            WaitlistEntry::query()->whereKey($request->integer('waitlist_entry_id'))->update(['status' => WaitlistStatus::Booked, 'booking_id' => $booking->id]);
        }

        return redirect()->route('admin.bookings.bookings.show', $booking)->with('success', $message);
    }

    public function show(Request $request, Booking $booking): Response
    {
        Gate::authorize('view', $booking);

        $canManage = $request->user()?->can('update', $booking) ?? false;

        return Inertia::render('admin/bookings/booking/Show', [
            'booking' => new BookingResource($booking->load(['resources', 'location', 'options', 'messages'])),
            // The rest of a repeating series, to move between them
            'series' => $booking->series_id === null ? [] : Booking::query()
                ->where('series_id', $booking->series_id)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Booking $other) => [
                    'id' => $other->id,
                    'when' => $other->starts_at?->setTimezone($other->timezone)->format('D j M Y, H:i'),
                    'status_label' => $other->status->label(),
                    'url' => route('admin.bookings.bookings.show', $other),
                    'current' => $other->is($booking),
                ])
                ->values(),
            'statuses' => BookingStatus::asSelectItems(),
            'messagePresets' => $this->messagePresets(),
            'payments' => $this->paymentSummary($request, $booking),
            'documents' => $this->documentSummary($request, $booking),
            'currency' => Service::CURRENCY,
            'canManage' => $canManage,
            'resources' => $canManage && $booking->needsTime()
                ? BookableResource::query()->where('is_active', true)->orderBy('name')->get()
                    ->map(fn (BookableResource $resource) => ['value' => $resource->id, 'label' => $resource->name])
                    ->values()
                : [],
        ]);
    }

    public function status(BookingStatusRequest $request, Booking $booking, BookingCreator $creator, BookingMessenger $messenger): RedirectResponse
    {
        $status = BookingStatus::from($request->string('status')->toString());

        if ($status === BookingStatus::Cancelled) {
            $creator->cancel($booking);
            $messenger->send($booking, MessageEvent::Cancelled);
        } else {
            $booking->update(['status' => $status]);
        }

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.bookings.flash.updated'));
    }

    /**
     * Moves a booking to another time or resource, e.g. dragged on the calendar
     */
    public function move(BookingMoveRequest $request, Booking $booking, BookingCreator $creator, BookingMessenger $messenger): RedirectResponse
    {
        $creator->move(
            $booking,
            $request->string('date')->toString(),
            $request->string('time')->toString(),
            $request->filled('resource_id') ? $request->integer('resource_id') : null,
        );

        $messenger->send($booking, MessageEvent::Changed);

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.bookings.flash.moved'));
    }

    /**
     * Sends the customer a one-off message, e.g. "your table is ready"
     */
    public function message(BookingMessageRequest $request, Booking $booking, BookingMessenger $messenger): RedirectResponse
    {
        $log = $messenger->sendCustom($booking, $request->string('body')->toString());

        return redirect()->back()->with(
            $log->status === MessageStatus::Skipped ? 'error' : 'success',
            __($log->status === MessageStatus::Skipped ? 'bookings.messages.custom.not_sent' : 'bookings.messages.custom.sent'),
        );
    }

    /**
     * Gives an enquiry a time, which confirms it
     */
    public function schedule(BookingScheduleRequest $request, Booking $booking, BookingCreator $creator, BookingMessenger $messenger): RedirectResponse
    {
        /** @var list<int|string> $resourceIds */
        $resourceIds = $request->validated('resource_ids') ?? [];

        $creator->schedule(
            $booking,
            $request->string('date')->toString(),
            $request->string('time')->toString(),
            $request->integer('duration_minutes'),
            array_map(intval(...), $resourceIds),
        );

        // An enquiry given a time is now a booking, so the customer is told as for any other
        $messenger->send($booking, MessageEvent::Booked);

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.bookings.flash.updated'));
    }

    /**
     * What's been paid, what's left, whether more can be asked for, the card kept for fees, and any
     * fee due, for those who can see payments
     *
     * @return array<string, mixed>|null
     */
    protected function paymentSummary(Request $request, Booking $booking): ?array
    {
        if (! ($request->user()?->can(BookingsPermission::ViewPayments) ?? false)) {
            return null;
        }

        $payments = app(BookingPayments::class);
        $outstanding = $payments->outstanding($booking);
        $suggested = $payments->suggested($booking);
        $tenant = tenant();
        $card = $payments->savedCard($booking);
        $cardLink = $booking->savedCards()->where('status', SavedCardStatus::Requested)->latest('id')->first();
        $fee = $payments->chargeableFee($booking);
        $suggestedFee = $fee === null ? null : $payments->feeFor($booking, $fee);

        return [
            'price' => $booking->price === null ? null : $payments->format($booking->price),
            'paid' => $payments->format($payments->paid($booking)),
            'outstanding' => $outstanding === null ? null : $payments->format($outstanding),
            'settled' => $outstanding === 0,
            'suggested' => $suggested === null ? null : number_format($suggested / 100, 2, '.', ''),
            'canRequest' => $tenant instanceof Tenant && $tenant->canTakePayments() && app(StripeGateway::class)->isConfigured(),
            'canManage' => $request->user()->can(BookingsPermission::ManagePayments),
            'methods' => array_values(array_map(fn (PaymentMethod $method) => $method->toSelectItem(), array_filter(PaymentMethod::cases(), fn (PaymentMethod $method) => $method->isInPerson()))),
            'list' => $booking->payments()->with('refunds')->latest('id')->get()->map(fn (Payment $payment) => BookingPaymentController::row($payment->setRelation('booking', $booking), $payments))->values(),
            'card' => $card === null ? null : ['label' => $card->label(), 'saved_at' => $card->saved_at?->setTimezone($booking->timezone)->format('D j M Y')],
            'cardLink' => $cardLink !== null && $cardLink->isOpen() ? route('bookings.cards.show', $cardLink->public_id) : null,
            'fee' => $fee === null ? null : [
                'purpose' => $fee->value,
                'label' => $fee->label(),
                'suggested' => $suggestedFee === null ? null : number_format($suggestedFee / 100, 2, '.', ''),
            ],
        ];
    }

    /**
     * The booking's quotes and its invoice, for those who can see payments
     *
     * @return array<string, mixed>|null
     */
    protected function documentSummary(Request $request, Booking $booking): ?array
    {
        if (! ($request->user()?->can(BookingsPermission::ViewPayments) ?? false)) {
            return null;
        }

        $payments = app(BookingPayments::class);
        $documents = app(BookingDocuments::class);
        $invoice = $documents->currentInvoice($booking);
        $owed = $invoice === null ? null : $documents->owed($invoice);

        return [
            'quotes' => $booking->quotes()->latest('id')->get()->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'number' => $quote->number,
                'total' => $payments->format($quote->total, $quote->currency),
                'status' => $quote->currentStatus()->value,
                'status_label' => $quote->currentStatus()->label(),
                'url' => route('admin.bookings.quotes.show', $quote),
            ])->values(),
            'invoice' => $invoice === null ? null : [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'total' => $payments->format($invoice->total, $invoice->currency),
                'owed' => $payments->format((int) $owed, $invoice->currency),
                'status' => $invoice->currentStatus((int) $owed)->value,
                'status_label' => $invoice->currentStatus((int) $owed)->label(),
                'url' => route('admin.bookings.invoices.show', $invoice),
            ],
            'canManage' => $request->user()->can(BookingsPermission::ManagePayments),
        ];
    }

    /**
     * Ready-made one-off messages, in the tenant's words
     *
     * @return list<string>
     */
    protected function messagePresets(): array
    {
        /** @var array<string, string> $presets */
        $presets = (array) __('bookings.messages.custom.presets');

        return array_map(fn (string $key) => app(Terminology::class)->translate("bookings.messages.custom.presets.{$key}"), array_keys($presets));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    protected function locationOptions(): array
    {
        return array_values(OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->orderBy('name')->get()
            ->map(fn (OrganisationUnit $location) => ['value' => $location->id, 'label' => $location->name])
            ->all());
    }
}

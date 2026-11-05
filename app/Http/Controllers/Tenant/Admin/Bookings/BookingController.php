<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\ResourceType;
use App\Enums\Bookings\WaitlistStatus;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Bookings\BookingIndexQuery;
use App\Http\Requests\Tenant\Admin\Bookings\BookingIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingMoveRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingScheduleRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingStatusRequest;
use App\Http\Requests\Tenant\Admin\Bookings\BookingStoreRequest;
use App\Http\Resources\Tenant\Admin\Bookings\BookingResource;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceOption;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Crm\OrganisationUnit;
use App\Services\Bookings\BookingCreator;
use App\Services\Industry\Terminology;
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
        /** @var array{date?: string|null, time?: string|null, walk_in?: bool|null, ignore_rules?: bool|null, session_id?: int|null, quantity?: int|null, option_ids?: list<int>, resource_ids?: list<int>, customer_name: string, customer_email?: string|null, customer_phone?: string|null, notes?: string|null} $details */
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
            'booking' => new BookingResource($booking->load(['resources', 'location', 'options'])),
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
            'currency' => Service::CURRENCY,
            'canManage' => $canManage,
            'resources' => $canManage && $booking->needsTime()
                ? BookableResource::query()->where('is_active', true)->orderBy('name')->get()
                    ->map(fn (BookableResource $resource) => ['value' => $resource->id, 'label' => $resource->name])
                    ->values()
                : [],
        ]);
    }

    public function status(BookingStatusRequest $request, Booking $booking, BookingCreator $creator): RedirectResponse
    {
        $status = BookingStatus::from($request->string('status')->toString());

        if ($status === BookingStatus::Cancelled) {
            $creator->cancel($booking);
        } else {
            $booking->update(['status' => $status]);
        }

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.bookings.flash.updated'));
    }

    /**
     * Moves a booking to another time or resource, e.g. dragged on the calendar
     */
    public function move(BookingMoveRequest $request, Booking $booking, BookingCreator $creator): RedirectResponse
    {
        $creator->move(
            $booking,
            $request->string('date')->toString(),
            $request->string('time')->toString(),
            $request->filled('resource_id') ? $request->integer('resource_id') : null,
        );

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.bookings.flash.moved'));
    }

    /**
     * Gives an enquiry a time, which confirms it
     */
    public function schedule(BookingScheduleRequest $request, Booking $booking, BookingCreator $creator): RedirectResponse
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

        return redirect()->back()->with('success', app(Terminology::class)->translate('bookings.bookings.flash.updated'));
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

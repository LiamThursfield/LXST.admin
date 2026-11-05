<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\ResourceType;
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
use App\Models\Crm\OrganisationUnit;
use App\Services\Bookings\BookingCreator;
use App\Services\Industry\Terminology;
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

    public function create(): Response
    {
        Gate::authorize('create', Booking::class);

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
        ]);
    }

    public function store(BookingStoreRequest $request, BookingCreator $creator): RedirectResponse
    {
        /** @var Service $service */
        $service = $request->service();
        /** @var array{date?: string|null, time?: string|null, walk_in?: bool|null, session_id?: int|null, quantity?: int|null, option_ids?: list<int>, resource_ids?: list<int>, customer_name: string, customer_email?: string|null, customer_phone?: string|null, notes?: string|null} $details */
        $details = $request->safe()->except(['service_id', 'location_id']);

        $booking = $creator->create($service, $request->location(), $details, $request->user());

        return redirect()->route('admin.bookings.bookings.show', $booking)
            ->with('success', app(Terminology::class)->translate('bookings.bookings.flash.created'));
    }

    public function show(Request $request, Booking $booking): Response
    {
        Gate::authorize('view', $booking);

        $canManage = $request->user()?->can('update', $booking) ?? false;

        return Inertia::render('admin/bookings/booking/Show', [
            'booking' => new BookingResource($booking->load(['resources', 'location', 'options'])),
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

    public function status(BookingStatusRequest $request, Booking $booking): RedirectResponse
    {
        $booking->update(['status' => BookingStatus::from($request->string('status')->toString())]);

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

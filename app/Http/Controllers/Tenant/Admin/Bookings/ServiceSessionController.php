<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceSessionIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceSessionStoreRequest;
use App\Http\Requests\Tenant\Admin\Bookings\ServiceSessionUpdateRequest;
use App\Http\Resources\Tenant\Admin\Bookings\ServiceSessionResource;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Crm\OrganisationUnit;
use App\Services\Bookings\BookingAvailability;
use App\Services\Industry\Terminology;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The timetable: the scheduled times of classes and events, which bookings take places on
 */
class ServiceSessionController extends Controller
{
    public function __construct(protected BookingAvailability $availability) {}

    public function index(ServiceSessionIndexRequest $request): Response
    {
        $terminology = app(Terminology::class);

        return Inertia::render('admin/bookings/session/Index', [
            'columns' => [
                ['accessorKey' => 'when', 'header' => __('bookings.sessions.columns.when')],
                ['accessorKey' => 'service_name', 'header' => $terminology->translate('bookings.sessions.columns.service')],
                ['accessorKey' => 'location_name', 'header' => $terminology->translate('bookings.sessions.columns.location')],
                ['accessorKey' => 'resource_names', 'header' => __('bookings.sessions.columns.resources')],
                ['accessorKey' => 'places_left', 'header' => __('bookings.sessions.columns.places')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'sessions' => ServiceSessionResource::collection(
                ServiceSession::query()
                    ->with(['service', 'location', 'resources'])
                    ->where('ends_at', '>=', now()->subDay())
                    ->orderBy('starts_at')
                    ->paginate($request->integer('per_page', 15))
            ),
            'canCreate' => $request->user()?->can('create', ServiceSession::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ServiceSession::class);

        return Inertia::render('admin/bookings/session/Create', $this->formOptions());
    }

    public function store(ServiceSessionStoreRequest $request): RedirectResponse
    {
        $this->save(new ServiceSession, $request);

        return redirect()->route('admin.bookings.sessions.index')
            ->with('success', __('bookings.sessions.flash.created'));
    }

    public function edit(ServiceSession $session): Response
    {
        Gate::authorize('update', $session);

        return Inertia::render('admin/bookings/session/Edit', [
            'session' => new ServiceSessionResource($session->load(['service', 'location', 'resources'])),
            ...$this->formOptions(),
        ]);
    }

    public function update(ServiceSessionUpdateRequest $request, ServiceSession $session): RedirectResponse
    {
        $this->save($session, $request);

        return redirect()->route('admin.bookings.sessions.index')
            ->with('success', __('bookings.sessions.flash.updated'));
    }

    /**
     * Only an entry nobody has booked can go, even if those bookings were cancelled, as they point to it
     */
    public function destroy(ServiceSession $session): RedirectResponse
    {
        Gate::authorize('delete', $session);

        if ($session->bookings()->exists()) {
            return redirect()->back()->with('error', app(Terminology::class)->translate('bookings.sessions.validation.has_bookings'));
        }

        $session->delete();

        return redirect()->back()->with('success', __('bookings.sessions.flash.deleted'));
    }

    /**
     * Refuses resources that are busy, or fewer places than are already taken. Moving a session moves
     * the bookings on it.
     */
    protected function save(ServiceSession $session, FormRequest $request): void
    {
        DB::transaction(function () use ($session, $request) {
            /** @var array{service_id: int|string, location_id?: int|string|null, date: string, time: string, duration_minutes: int|string, capacity?: int|string|null, resource_ids?: list<int|string>} $validated */
            $validated = $request->validated();
            $resourceIds = array_map(intval(...), $validated['resource_ids'] ?? []);
            $hasLocations = tenant()?->hasModule(TenantModule::Crm) ?? false;
            $locationId = $hasLocations ? ($validated['location_id'] ?? null) : $session->location_id;
            $location = OrganisationUnit::query()->find($locationId);
            $start = CarbonImmutable::parse("{$validated['date']} {$validated['time']}", $this->availability->timezoneFor($location));
            $end = $start->addMinutes((int) $validated['duration_minutes']);

            BookableResource::query()->whereKey($resourceIds)->orderBy('id')->lockForUpdate()->get();

            if (! $this->availability->isFree($resourceIds, $start, $end, $session->exists ? $session->id : null)) {
                throw ValidationException::withMessages(['resource_ids' => __('bookings.sessions.validation.resource_taken')]);
            }

            $session->fill([
                'service_id' => (int) $validated['service_id'],
                'location_id' => $locationId,
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'capacity' => $validated['capacity'] ?? null,
            ]);

            if ($session->exists) {
                $total = $session->capacity ?? $session->service->capacity ?? 0;

                if ($this->placesTaken($session) > $total) {
                    throw ValidationException::withMessages(['capacity' => __('bookings.sessions.validation.below_taken')]);
                }
            }

            $session->save();
            $session->resources()->sync($resourceIds);
            $session->bookings()->update([
                'location_id' => $locationId,
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'blocked_from' => $start->utc(),
                'blocked_until' => $end->utc(),
            ]);
        });
    }

    protected function placesTaken(ServiceSession $session): int
    {
        return (int) $session->bookings()->whereIn('status', BookingStatus::holdingTime())->sum('quantity');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $hasLocations = tenant()?->hasModule(TenantModule::Crm) ?? false;

        return [
            'services' => Service::query()
                ->whereIn('booking_type', [BookingType::GroupClass, BookingType::Ticket])
                ->orderBy('name')
                ->get()
                ->map(fn (Service $service) => [
                    'value' => $service->id,
                    'label' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'capacity' => $service->capacity,
                ])
                ->values(),
            'hasLocations' => $hasLocations,
            'locations' => $hasLocations
                ? OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->orderBy('name')->get()
                    ->map(fn (OrganisationUnit $location) => ['value' => $location->id, 'label' => $location->name])
                    ->values()
                : [],
            'resources' => BookableResource::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (BookableResource $resource) => ['value' => $resource->id, 'label' => $resource->name])
                ->values(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\WaitlistStatus;
use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\WaitlistIndexRequest;
use App\Http\Requests\Tenant\Admin\Bookings\WaitlistStoreRequest;
use App\Http\Resources\Tenant\Admin\Bookings\WaitlistEntryResource;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Crm\OrganisationUnit;
use App\Services\Bookings\BookingAvailability;
use App\Services\Bookings\BookingCreator;
use App\Services\Bookings\BookingMessenger;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * People waiting for a full class or event, or for any time on a date. Staff book them when there's
 * room; messages offering it to them come with #179.
 */
class WaitlistController extends Controller
{
    public function index(WaitlistIndexRequest $request): Response
    {
        $terminology = app(Terminology::class);

        return Inertia::render('admin/bookings/waitlist/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'customer_name', 'header' => $terminology->translate('bookings.waitlist.columns.customer')],
                ['accessorKey' => 'service_name', 'header' => $terminology->translate('bookings.waitlist.columns.service')],
                ['accessorKey' => 'wanted', 'header' => __('bookings.waitlist.columns.wanted')],
                ['accessorKey' => 'quantity', 'header' => __('bookings.waitlist.columns.quantity')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.waitlist.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            // First come, first served, so the earliest waiting is at the top
            'entries' => WaitlistEntryResource::collection(
                QueryBuilder::for(WaitlistEntry::query()->with(['service', 'session.location', 'session.service']), $request)
                    ->allowedFilters(
                        AllowedFilter::exact('status'),
                        AllowedFilter::exact('session_id'),
                    )
                    ->defaultSort('created_at')
                    ->paginate($request->integer('per_page', 15))
                    ->appends($request->query())
            ),
            'statuses' => WaitlistStatus::asSelectItems(),
            'canCreate' => $request->user()?->can('create', WaitlistEntry::class) ?? false,
        ]);
    }

    public function create(BookingAvailability $availability): Response
    {
        Gate::authorize('create', WaitlistEntry::class);

        $hasLocations = tenant()?->hasModule(TenantModule::Crm) ?? false;

        return Inertia::render('admin/bookings/waitlist/Create', [
            'services' => Service::query()->where('booking_type', '!=', BookingType::Enquiry)->orderBy('name')->get()
                ->map(fn (Service $service) => [
                    'value' => $service->id,
                    'label' => $service->name,
                    'uses_sessions' => $service->booking_type->usesSessions(),
                    'sessions' => $service->booking_type->usesSessions()
                        ? ServiceSession::query()->where('service_id', $service->id)->where('starts_at', '>', now())->orderBy('starts_at')->with('location')->get()
                            ->map(fn (ServiceSession $session) => [
                                'value' => $session->id,
                                'label' => $session->starts_at->copy()->setTimezone($availability->timezoneFor($session->location))->format('D j M Y, H:i'),
                            ])
                            ->values()
                        : [],
                ])
                ->values(),
            'hasLocations' => $hasLocations,
            'locations' => $hasLocations
                ? OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->orderBy('name')->get()
                    ->map(fn (OrganisationUnit $location) => ['value' => $location->id, 'label' => $location->name])
                    ->values()
                : [],
        ]);
    }

    public function store(WaitlistStoreRequest $request): RedirectResponse
    {
        WaitlistEntry::query()->create([
            ...$request->validated(),
            'quantity' => max(1, $request->integer('quantity', 1)),
            'status' => WaitlistStatus::Waiting,
            'created_by_id' => $request->user()?->id,
        ]);

        return redirect()->route('admin.bookings.waitlist.index')
            ->with('success', __('bookings.waitlist.flash.added'));
    }

    /**
     * Books a waiting place on a timetable entry straight away. Someone waiting for a date goes to
     * the booking form, filled in, to choose from the times free.
     */
    public function book(Request $request, WaitlistEntry $entry, BookingCreator $creator): RedirectResponse
    {
        Gate::authorize('update', $entry);
        abort_unless($entry->status === WaitlistStatus::Waiting, 404);

        if ($entry->session_id === null) {
            return redirect()->route('admin.bookings.bookings.create', ['waitlist_entry_id' => $entry->id]);
        }

        $booking = DB::transaction(function () use ($entry, $creator, $request) {
            $service = $entry->service;
            abort_if($service === null || $service->trashed(), 404);

            $booking = $creator->create($service, null, [
                'session_id' => $entry->session_id,
                'quantity' => $entry->quantity,
                'customer_name' => $entry->customer_name,
                'customer_email' => $entry->customer_email,
                'customer_phone' => $entry->customer_phone,
                'notes' => $entry->notes,
                // They asked when the place was within the rules, so it's booked now it's free
                'ignore_rules' => true,
            ], $request->user());

            $entry->update(['status' => WaitlistStatus::Booked, 'booking_id' => $booking->id]);

            return $booking;
        });

        app(BookingMessenger::class)->send($booking, MessageEvent::Booked);

        return redirect()->route('admin.bookings.bookings.show', $booking)
            ->with('success', app(Terminology::class)->translate('bookings.waitlist.flash.booked'));
    }

    /**
     * Takes someone off the waitlist, keeping the entry
     */
    public function destroy(WaitlistEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $entry);

        $entry->update(['status' => WaitlistStatus::Removed]);

        return redirect()->back()->with('success', __('bookings.waitlist.flash.removed'));
    }
}

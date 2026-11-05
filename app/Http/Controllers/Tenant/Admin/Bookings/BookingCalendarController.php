<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\TimeOffStatus;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\BookingCalendarRequest;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\ServiceSession;
use App\Models\Crm\OrganisationUnit;
use App\Services\Bookings\BookingAvailability;
use App\Services\Bookings\ResourceSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A day of everyone and everything side by side, or a week of one of them. Each column has its
 * bookings and sessions, placed in minutes from the start of its date, with the hours worked shaded.
 * Times are in the chosen location's timezone, or the app's.
 */
class BookingCalendarController extends Controller
{
    /** Shown when nothing on the calendar says otherwise */
    protected const int DEFAULT_START_MINUTES = 8 * 60;

    protected const int DEFAULT_END_MINUTES = 18 * 60;

    public function __construct(
        protected ResourceSchedule $schedule,
        protected BookingAvailability $availability,
    ) {}

    public function __invoke(BookingCalendarRequest $request): Response
    {
        $hasLocations = tenant()?->hasModule(TenantModule::Crm) ?? false;
        $location = $hasLocations && $request->filled('location_id') ? OrganisationUnit::query()->find($request->integer('location_id')) : null;
        $timezone = $this->availability->timezoneFor($location);
        $view = $request->validated('view') ?? BookingCalendarRequest::VIEW_DAY;
        /** @var string|null $dateInput */
        $dateInput = $request->validated('date');
        $date = $dateInput === null ? CarbonImmutable::now($timezone)->startOfDay() : CarbonImmutable::parse($dateInput, $timezone);

        $resources = BookableResource::query()
            ->where('is_active', true)
            ->when($location, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('location_id')
                ->orWhere('location_id', $location?->id)))
            ->orderBy('name')
            ->get()
            ->sortBy(fn (BookableResource $resource) => $resource->type->isPerson() ? 0 : 1)
            ->values();

        if ($view === BookingCalendarRequest::VIEW_WEEK) {
            $date = $date->startOfWeek();
            $chosen = $resources->firstWhere('id', $request->integer('resource_id')) ?? $resources->first();
            $dates = array_map(fn (int $offset) => $date->addDays($offset), range(0, 6));
            $columns = $chosen === null ? [] : array_map(fn (CarbonImmutable $day) => ['resource' => $chosen, 'date' => $day], $dates);
            $previous = $date->subWeek();
            $next = $date->addWeek();
        } else {
            $chosen = null;
            $dates = [$date];
            $columns = $resources->map(fn (BookableResource $resource) => ['resource' => $resource, 'date' => $date])->values()->all();
            $previous = $date->subDay();
            $next = $date->addDay();
        }

        $columns = $this->fill(array_values($columns), $dates);

        return Inertia::render('admin/bookings/calendar/Index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'previousDate' => $previous->toDateString(),
            'nextDate' => $next->toDateString(),
            'timezone' => $timezone,
            'columns' => $columns,
            ...$this->window($columns),
            'slotMinutes' => BookingAvailability::SLOT_MINUTES,
            'hasLocations' => $hasLocations,
            'locationId' => $location?->id,
            'locations' => $hasLocations
                ? OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->orderBy('name')->get()
                    ->map(fn (OrganisationUnit $unit) => ['value' => $unit->id, 'label' => $unit->name])
                    ->values()
                : [],
            'resourceId' => $chosen?->id,
            'resources' => $resources->map(fn (BookableResource $resource) => ['value' => $resource->id, 'label' => $resource->name])->values(),
            'canManage' => $request->user()?->can('create', Booking::class) ?? false,
        ]);
    }

    /**
     * Each column's hours, bookings and sessions, loaded for all the columns at once
     *
     * @param  list<array{resource: BookableResource, date: CarbonImmutable}>  $columns
     * @param  list<CarbonImmutable>  $dates
     * @return list<array<string, mixed>>
     */
    protected function fill(array $columns, array $dates): array
    {
        if ($columns === []) {
            return [];
        }

        $from = $dates[0];
        $until = $dates[count($dates) - 1]->addDay();
        $resourceIds = array_values(array_unique(array_map(fn (array $column) => $column['resource']->id, $columns)));

        $loaded = BookableResource::query()
            ->whereKey($resourceIds)
            ->with([
                'workingHours',
                'shifts' => fn ($query) => $query->whereBetween('works_on', [$from->toDateString(), $until->toDateString()]),
                'timeOff' => fn ($query) => $query->where('status', TimeOffStatus::Approved)->whereDate('starts_on', '<=', $until->toDateString())->whereDate('ends_on', '>=', $from->toDateString()),
            ])
            ->get()
            ->keyBy('id');

        $bookings = Booking::query()
            ->whereIn('status', BookingStatus::holdingTime())
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->whereHas('resources', fn (Builder $query) => $query->whereKey($resourceIds))
            ->with('resources')
            ->get();

        $sessions = ServiceSession::query()
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->whereHas('resources', fn (Builder $query) => $query->whereKey($resourceIds))
            ->with(['resources', 'service'])
            ->withSum(['bookings' => fn ($query) => $query->whereIn('status', BookingStatus::holdingTime())], 'quantity')
            ->get();

        return array_map(function (array $column) use ($loaded, $bookings, $sessions) {
            /** @var BookableResource $resource */
            $resource = $loaded->get($column['resource']->id);
            $day = $column['date'];
            $schedule = $this->schedule->on($resource, $day);

            return [
                'key' => "{$resource->id}-{$day->toDateString()}",
                'resource_id' => $resource->id,
                'resource_name' => $resource->name,
                'type_label' => $resource->type->label(),
                'date' => $day->toDateString(),
                'day_of_week' => $day->dayOfWeekIso,
                'off' => $schedule['source'] === ResourceSchedule::SOURCE_OFF,
                // A space, piece of equipment or vehicle with no hours of its own is free when the location is open
                'follows_location' => ! $resource->type->isPerson() && $resource->workingHours->isEmpty(),
                'periods' => array_map(fn (array $period) => [
                    'start' => $this->minutesInto($day, $day->setTimeFromTimeString($period['starts_at'])),
                    'end' => $this->minutesInto($day, $this->periodEnd($day, $period['starts_at'], $period['ends_at'])),
                ], $schedule['periods']),
                'items' => [
                    ...$this->bookingItems($bookings, $resource, $day),
                    ...$this->sessionItems($sessions, $resource, $day),
                ],
            ];
        }, $columns);
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @return list<array<string, mixed>>
     */
    protected function bookingItems(Collection $bookings, BookableResource $resource, CarbonImmutable $day): array
    {
        return array_values($bookings
            ->filter(fn (Booking $booking) => $booking->resources->contains('id', $resource->id) && $this->touches($booking->starts_at, $booking->ends_at, $day))
            ->map(fn (Booking $booking) => [
                'kind' => 'booking',
                'id' => $booking->id,
                'title' => $booking->customer_name,
                'subtitle' => $booking->service_name,
                'status' => $booking->status->value,
                'is_walk_in' => $booking->is_walk_in,
                'start' => $this->minutesInto($day, $booking->starts_at ?? $day),
                'end' => $this->minutesInto($day, $booking->ends_at ?? $day),
                'movable' => $booking->isMovable(),
                'url' => route('admin.bookings.bookings.show', $booking),
            ])
            ->all());
    }

    /**
     * @param  Collection<int, ServiceSession>  $sessions
     * @return list<array<string, mixed>>
     */
    protected function sessionItems(Collection $sessions, BookableResource $resource, CarbonImmutable $day): array
    {
        return array_values($sessions
            ->filter(fn (ServiceSession $session) => $session->resources->contains('id', $resource->id) && $this->touches($session->starts_at, $session->ends_at, $day))
            ->map(fn (ServiceSession $session) => [
                'kind' => 'session',
                'id' => $session->id,
                'title' => $session->service?->name,
                'booked' => (int) $session->getAttribute('bookings_sum_quantity'),
                'start' => $this->minutesInto($day, $session->starts_at),
                'end' => $this->minutesInto($day, $session->ends_at),
                'movable' => false,
                'url' => route('admin.bookings.sessions.edit', $session),
            ])
            ->all());
    }

    /**
     * The hours to show: the earliest start to the latest end of anything on the calendar, in whole
     * hours, and the working day by default
     *
     * @param  list<array<string, mixed>>  $columns
     * @return array{startMinutes: int, endMinutes: int}
     */
    protected function window(array $columns): array
    {
        $starts = [self::DEFAULT_START_MINUTES];
        $ends = [self::DEFAULT_END_MINUTES];

        foreach ($columns as $column) {
            /** @var list<array{start: int, end: int}> $spans */
            $spans = [...$column['periods'], ...$column['items']];

            foreach ($spans as $span) {
                $starts[] = $span['start'];
                $ends[] = $span['end'];
            }
        }

        return [
            'startMinutes' => max(0, intdiv(min($starts), 60) * 60),
            'endMinutes' => min(24 * 60, (int) ceil(max($ends) / 60) * 60),
        ];
    }

    protected function touches(?CarbonInterface $start, ?CarbonInterface $end, CarbonImmutable $day): bool
    {
        return $start !== null && $end !== null && $start->lessThan($day->addDay()) && $end->greaterThan($day);
    }

    /**
     * Minutes from the start of the date, in its timezone, kept within the date
     */
    protected function minutesInto(CarbonImmutable $day, CarbonInterface $moment): int
    {
        $minutes = (int) floor(($moment->getTimestamp() - $day->getTimestamp()) / 60);

        return max(0, min((int) floor(($day->addDay()->getTimestamp() - $day->getTimestamp()) / 60), $minutes));
    }

    protected function periodEnd(CarbonImmutable $day, string $start, string $end): CarbonImmutable
    {
        $startAt = $day->setTimeFromTimeString($start);
        $endAt = $day->setTimeFromTimeString($end);

        return $endAt->lessThanOrEqualTo($startAt) ? $endAt->addDay() : $endAt;
    }
}

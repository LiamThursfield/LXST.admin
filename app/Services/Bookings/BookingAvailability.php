<?php

namespace App\Services\Bookings;

use App\Data\Bookings\BookingSlot;
use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\ResourceType;
use App\Enums\BookingType;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Crm\OrganisationUnit;
use App\Services\Crm\LocationHours;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Works out when a service can be booked, on each request rather than from stored slots: from the
 * location's hours, each resource's schedule, and what's already booked. Times are read in the
 * location's timezone, or the app's without a location.
 *
 * A location with no hours is treated as always open. A space, piece of equipment or vehicle with
 * no working hours follows the location; a person with none isn't working.
 *
 * @phpstan-type Interval array{0: CarbonImmutable, 1: CarbonImmutable}
 */
class BookingAvailability
{
    /** How far apart the times offered are */
    public const int SLOT_MINUTES = 15;

    /** When a whole-day hire starts if the location has no hours to go by */
    protected const string DEFAULT_DAY_START = '09:00';

    public function __construct(
        protected ResourceSchedule $schedule,
        protected LocationHours $locationHours,
    ) {}

    public function timezoneFor(?OrganisationUnit $location): string
    {
        return $location?->localTimezone() ?? (string) config('app.timezone');
    }

    /**
     * The times a service can start on a date, for a service booked at a time rather than on a session
     *
     * @param  list<int>  $optionIds
     * @param  list<int>  $preferredResourceIds
     * @return list<BookingSlot>
     */
    public function slots(Service $service, ?OrganisationUnit $location, string $date, int $quantity = 1, array $optionIds = [], array $preferredResourceIds = []): array
    {
        $starts = array_values(array_filter(
            $this->startTimes($service, $location, $date),
            fn (CarbonImmutable $start) => $start->isFuture(),
        ));

        if ($starts === []) {
            return [];
        }

        // Loaded once, as a date can have dozens of times to try
        $candidates = $this->candidates($service, $location, $service->requiredResourceTypes(), $starts[0], $quantity, $preferredResourceIds);
        $adjustment = $this->durationAdjustment($service, $optionIds);
        $busy = $this->busyRanges($candidates, $starts[0], $quantity);

        return array_values(array_filter(array_map(
            fn (CarbonImmutable $start) => $this->fit($service, $location, $start, $quantity, $adjustment, $candidates, $busy),
            $starts,
        )));
    }

    /**
     * Whether, and with what, the service fits at a time
     *
     * @param  list<int>  $optionIds
     * @param  list<int>  $preferredResourceIds
     */
    public function slotAt(Service $service, ?OrganisationUnit $location, CarbonImmutable $start, int $quantity = 1, array $optionIds = [], array $preferredResourceIds = []): ?BookingSlot
    {
        $candidates = $this->candidates($service, $location, $service->requiredResourceTypes(), $start, $quantity, $preferredResourceIds);

        return $this->fit(
            $service,
            $location,
            $start,
            $quantity,
            $this->durationAdjustment($service, $optionIds),
            $candidates,
            $this->busyRanges($candidates, $start, $quantity),
        );
    }

    /**
     * Takes one resource of each kind the service needs, a person first, as their own length decides
     * how long the others are needed for
     *
     * @param  array<string, list<BookableResource>>  $candidates
     * @param  array<int, list<Interval>>  $busy
     */
    protected function fit(Service $service, ?OrganisationUnit $location, CarbonImmutable $start, int $quantity, int $adjustment, array $candidates, array $busy): ?BookingSlot
    {
        $types = $service->requiredResourceTypes();

        if ($types === []) {
            return null;
        }

        usort($types, fn (ResourceType $first, ResourceType $second) => $second->isPerson() <=> $first->isPerson());

        $chosen = [];
        $end = null;

        foreach ($types as $type) {
            $resource = null;

            foreach ($candidates[$type->value] ?? [] as $candidate) {
                $candidateEnd = $end ?? $this->endFor($service, $candidate, $start, $quantity, $adjustment);
                [$from, $until] = $this->blockedRange($service, $start, $candidateEnd);

                if (
                    $this->locationIsOpen($service, $location, $start, $candidateEnd)
                    && $this->isWorking($service, $candidate, $start, $candidateEnd)
                    && ! $this->overlaps($busy[$candidate->id] ?? [], $from, $until)
                ) {
                    $resource = $candidate;
                    $end = $candidateEnd;

                    break;
                }
            }

            if ($resource === null || $end === null) {
                return null;
            }

            $chosen[] = $resource;
        }

        [$blockedFrom, $blockedUntil] = $this->blockedRange($service, $start, $end);

        return new BookingSlot($start, $end, $blockedFrom, $blockedUntil, $chosen);
    }

    /**
     * @param  list<int>  $optionIds
     */
    protected function durationAdjustment(Service $service, array $optionIds): int
    {
        return $optionIds === [] ? 0 : (int) $service->options()->whereKey($optionIds)->sum('duration_adjustment');
    }

    /**
     * What's already tying up each candidate around a time: bookings, and sessions they're on
     *
     * @param  array<string, list<BookableResource>>  $candidates
     * @return array<int, list<Interval>>
     */
    protected function busyRanges(array $candidates, CarbonImmutable $start, int $quantity): array
    {
        $ids = array_map(fn (BookableResource $resource) => $resource->id, array_merge(...array_values($candidates) ?: [[]]));
        $from = $start->subDays(2);
        $until = $start->addDays($quantity + 2);
        $busy = [];

        if ($ids === []) {
            return $busy;
        }

        Booking::query()
            ->whereIn('status', BookingStatus::holdingTime())
            ->where('blocked_from', '<', $until->utc())
            ->where('blocked_until', '>', $from->utc())
            ->whereHas('resources', fn (Builder $query) => $query->whereKey($ids))
            ->with('resources')
            ->get()
            ->each(function (Booking $booking) use (&$busy) {
                foreach ($booking->resources as $resource) {
                    $busy[$resource->id][] = [CarbonImmutable::parse($booking->blocked_from), CarbonImmutable::parse($booking->blocked_until)];
                }
            });

        ServiceSession::query()
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->whereHas('resources', fn (Builder $query) => $query->whereKey($ids))
            ->with('resources')
            ->get()
            ->each(function (ServiceSession $session) use (&$busy) {
                foreach ($session->resources as $resource) {
                    $busy[$resource->id][] = [CarbonImmutable::parse($session->starts_at), CarbonImmutable::parse($session->ends_at)];
                }
            });

        return $busy;
    }

    /**
     * @param  list<Interval>  $ranges
     */
    protected function overlaps(array $ranges, CarbonImmutable $from, CarbonImmutable $until): bool
    {
        foreach ($ranges as [$rangeFrom, $rangeUntil]) {
            if ($rangeFrom->lessThan($until) && $rangeUntil->greaterThan($from)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether none of the resources are tied up by a booking or session in a range
     *
     * @param  list<int>  $resourceIds
     */
    public function isFree(array $resourceIds, CarbonImmutable $from, CarbonImmutable $until, ?int $exceptSessionId = null, ?int $exceptBookingId = null): bool
    {
        if ($resourceIds === []) {
            return true;
        }

        $booked = Booking::query()
            ->when($exceptBookingId, fn (Builder $query) => $query->whereKeyNot($exceptBookingId))
            ->whereIn('status', BookingStatus::holdingTime())
            ->where('blocked_from', '<', $until->utc())
            ->where('blocked_until', '>', $from->utc())
            ->whereHas('resources', fn (Builder $query) => $query->whereKey($resourceIds))
            ->exists();

        if ($booked) {
            return false;
        }

        return ! ServiceSession::query()
            ->when($exceptSessionId, fn (Builder $query) => $query->whereKeyNot($exceptSessionId))
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->whereHas('resources', fn (Builder $query) => $query->whereKey($resourceIds))
            ->exists();
    }

    /**
     * The upcoming sessions of a class or event with enough places left
     *
     * @return Collection<int, ServiceSession>
     */
    public function sessions(Service $service, ?OrganisationUnit $location, int $quantity = 1): Collection
    {
        return ServiceSession::query()
            ->where('service_id', $service->id)
            ->when($location, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('location_id')
                ->orWhere('location_id', $location?->id)))
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (ServiceSession $session) => $this->placesLeft($session) >= $quantity)
            ->values();
    }

    public function placesLeft(ServiceSession $session): int
    {
        $taken = (int) $session->bookings()->whereIn('status', BookingStatus::holdingTime())->sum('quantity');

        return max(0, ($session->capacity ?? $session->service->capacity ?? 0) - $taken);
    }

    /**
     * The times worth trying on a date: every SLOT_MINUTES through the location's hours, or a single
     * start for a hire by the day
     *
     * @return list<CarbonImmutable>
     */
    protected function startTimes(Service $service, ?OrganisationUnit $location, string $date): array
    {
        $timezone = $this->timezoneFor($location);
        $dayStart = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->addDay();
        $windows = $location !== null && $this->locationHasHours($location)
            ? $this->locationIntervals($location, $dayStart->subDay(), $dayEnd)
            : [[$dayStart, $dayEnd]];

        if ($service->hire_unit === HireUnit::Day) {
            if (! $this->locationHasHours($location)) {
                return [CarbonImmutable::parse("{$date} ".self::DEFAULT_DAY_START, $timezone)];
            }

            foreach ($windows as [$from]) {
                if ($from->greaterThanOrEqualTo($dayStart) && $from->lessThan($dayEnd)) {
                    return [$from];
                }
            }

            return [];
        }

        $starts = [];

        foreach ($windows as [$from, $until]) {
            for ($start = $from; $start->lessThan($until); $start = $start->addMinutes(self::SLOT_MINUTES)) {
                if ($start->greaterThanOrEqualTo($dayStart) && $start->lessThan($dayEnd)) {
                    $starts[] = $start;
                }
            }
        }

        return $starts;
    }

    /**
     * Those able to do the service at the location, by type, and big enough for a party. The smallest
     * table that fits comes first, so a couple don't take the table for eight.
     *
     * @param  list<ResourceType>  $types
     * @param  list<int>  $preferredResourceIds
     * @return array<string, list<BookableResource>>
     */
    protected function candidates(Service $service, ?OrganisationUnit $location, array $types, CarbonImmutable $start, int $quantity, array $preferredResourceIds): array
    {
        $isTable = $service->booking_type === BookingType::Table;
        $from = $start->subDay()->toDateString();
        $to = $start->addDays(max($quantity, 1) + 1)->toDateString();

        $resources = $service->resources()
            ->where('is_active', true)
            ->when($location, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('location_id')
                ->orWhere('location_id', $location?->id)))
            ->when($isTable, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('capacity')
                ->orWhere('capacity', '>=', $quantity)))
            ->with([
                'workingHours',
                'shifts' => fn ($query) => $query->whereBetween('works_on', [$from, $to]),
                'timeOff' => fn ($query) => $query->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from),
            ])
            ->orderBy('name')
            ->get();

        if ($isTable) {
            $resources = $resources->sortBy(fn (BookableResource $resource) => $resource->capacity ?? PHP_INT_MAX)->values();
        }

        $byType = [];

        foreach ($types as $type) {
            $ofType = $resources->filter(fn (BookableResource $resource) => $resource->type === $type);
            $preferred = $ofType->filter(fn (BookableResource $resource) => in_array($resource->id, $preferredResourceIds, true));

            $byType[$type->value] = array_values(($preferred->isNotEmpty() ? $preferred : $ofType)->all());
        }

        return $byType;
    }

    protected function endFor(Service $service, BookableResource $resource, CarbonImmutable $start, int $quantity, int $adjustment): CarbonImmutable
    {
        if ($service->hire_unit === HireUnit::Day) {
            return $start->addDays($quantity);
        }

        if ($service->hire_unit === HireUnit::Hour) {
            return $start->addMinutes(max(1, $quantity * 60 + $adjustment));
        }

        /** @var object{duration_minutes: int|null}|null $pivot */
        $pivot = $resource->getRelationValue('pivot');
        $length = $pivot->duration_minutes ?? $service->duration_minutes ?? 0;

        return $start->addMinutes(max(1, $length + $adjustment));
    }

    /**
     * What a booking ties up: its time, with travel and buffers before and buffers after
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function blockedRange(Service $service, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            $start->subMinutes($service->buffer_before_minutes + ($service->travel_minutes ?? 0)),
            $end->addMinutes($service->buffer_after_minutes),
        ];
    }

    protected function locationHasHours(?OrganisationUnit $location): bool
    {
        return $location !== null
            && ($location->openingHours->isNotEmpty() || $location->openingHourExceptions->isNotEmpty());
    }

    protected function locationIsOpen(Service $service, ?OrganisationUnit $location, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if ($location === null || ! $this->locationHasHours($location)) {
            return true;
        }

        if ($service->hire_unit === HireUnit::Day) {
            foreach ($this->datesBetween($start, $end) as $date) {
                if ($this->locationHours->periodsOn($location, $date) === []) {
                    return false;
                }
            }

            return true;
        }

        return $this->contains($this->locationIntervals($location, $start->startOfDay()->subDay(), $end), $start, $end);
    }

    /**
     * Whether the resource is working for the whole time, or on every day of a hire by the day
     */
    protected function isWorking(Service $service, BookableResource $resource, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $followsLocation = ! $resource->type->isPerson() && $resource->workingHours->isEmpty();

        foreach ($this->datesBetween($start, $end) as $date) {
            if ($this->schedule->on($resource, $date)['source'] === ResourceSchedule::SOURCE_OFF) {
                return false;
            }
        }

        if ($followsLocation) {
            return true;
        }

        if ($service->hire_unit === HireUnit::Day) {
            foreach ($this->datesBetween($start, $end) as $date) {
                if ($this->schedule->on($resource, $date)['periods'] === []) {
                    return false;
                }
            }

            return true;
        }

        $intervals = [];

        for ($date = $start->startOfDay()->subDay(); $date->lessThan($end); $date = $date->addDay()) {
            foreach ($this->schedule->on($resource, $date)['periods'] as $period) {
                $intervals[] = $this->interval($date, $period['starts_at'], $period['ends_at']);
            }
        }

        return $this->contains($intervals, $start, $end);
    }

    /**
     * The dates a booking touches. One ending exactly at midnight, or a whole-day hire ending at the
     * time the next day it started, doesn't touch that last date.
     *
     * @return list<CarbonImmutable>
     */
    protected function datesBetween(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $dates = [];

        for ($date = $start->startOfDay(); $date->lessThan($end) && ($dates === [] || $date->addDay()->lessThanOrEqualTo($end)); $date = $date->addDay()) {
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * The location's opening periods from one date up to a moment, in its timezone
     *
     * @return list<Interval>
     */
    protected function locationIntervals(OrganisationUnit $location, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $intervals = [];

        for ($date = $from; $date->lessThan($until); $date = $date->addDay()) {
            foreach ($this->locationHours->periodsOn($location, $date) as $period) {
                $intervals[] = $this->interval($date, $period['opens_at'], $period['closes_at']);
            }
        }

        return $intervals;
    }

    /**
     * A wall clock period on a date. An end at or before the start runs into the next day.
     *
     * @return Interval
     */
    protected function interval(CarbonImmutable $date, string $from, string $until): array
    {
        $start = $date->setTimeFromTimeString($from);
        $end = $date->setTimeFromTimeString($until);

        return [$start, $end->lessThanOrEqualTo($start) ? $end->addDay() : $end];
    }

    /**
     * Whether periods cover a time, with those that run into each other counting as one
     *
     * @param  list<Interval>  $intervals
     */
    protected function contains(array $intervals, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        usort($intervals, fn (array $first, array $second) => $first[0] <=> $second[0]);

        $covered = $start;

        foreach ($intervals as [$from, $until]) {
            if ($from->greaterThan($covered)) {
                break;
            }

            if ($until->greaterThan($covered)) {
                $covered = $until;
            }

            if ($covered->greaterThanOrEqualTo($end)) {
                return true;
            }
        }

        return false;
    }
}

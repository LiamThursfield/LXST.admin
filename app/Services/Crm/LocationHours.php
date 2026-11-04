<?php

namespace App\Services\Crm;

use App\Models\Crm\OpeningHour;
use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;

/**
 * Works out when a location is open from its weekly hours and exceptions, on the clock of the
 * location's own timezone. A period belongs to the day it starts on, so a Friday night running
 * past midnight is still Friday's, and a closure on the Saturday doesn't cut it short.
 *
 * @phpstan-type Period array{opens_at: string, closes_at: string, label: string|null}
 */
class LocationHours
{
    /** How far ahead to look for the next opening, so a seasonal closure still finds its reopening */
    protected const int SEARCH_DAYS = 366;

    /** Open for longer than this without a break, a location has no closing time worth showing */
    protected const int ALWAYS_OPEN_DAYS = 7;

    /** Schema.org's day names, by ISO day of the week. Never translated. */
    protected const array SCHEMA_ORG_DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** Schema.org's way of writing the end of a day that's open all day */
    protected const string SCHEMA_ORG_END_OF_DAY = '23:59';

    /**
     * The periods for a date: the exception's if one covers it, otherwise that weekday's
     *
     * @return list<Period>
     */
    public function periodsOn(OrganisationUnit $location, CarbonInterface $date): array
    {
        $day = $date->toDateString();
        $exception = $location->openingHourExceptions->first(
            fn (OpeningHourException $exception) => $exception->covers($day)
        );

        $periods = $exception !== null
            ? array_map($this->period(...), $exception->periods ?? [])
            : $location->openingHours
                ->where('day_of_week', $date->dayOfWeekIso)
                ->map(fn (OpeningHour $hour) => $this->period($hour->only(['opens_at', 'closes_at', 'label'])))
                ->all();

        return array_values(collect($periods)->sortBy('opens_at')->all());
    }

    /**
     * Whether the location is open at a moment, and when that next changes. `open_now` is null when
     * it doesn't apply: the location is by appointment, or hasn't published any hours.
     *
     * @return array{open_now: bool|null, opens_at: string|null, closes_at: string|null, today: list<Period>}
     */
    public function status(OrganisationUnit $location, ?CarbonInterface $at = null): array
    {
        $now = CarbonImmutable::instance($at ?? now())->setTimezone($location->localTimezone());
        $status = ['open_now' => null, 'opens_at' => null, 'closes_at' => null, 'today' => $this->periodsOn($location, $now)];

        if (! ($location->location_type?->canBeOpenNow() ?? true) || ! $this->hasHours($location)) {
            return $status;
        }

        $closes = null;

        foreach ($this->intervals($location, $now->subDay()->startOfDay()) as [$start, $end]) {
            if ($end <= $now) {
                continue;
            }

            if ($closes === null && $start > $now) {
                return [...$status, 'open_now' => false, 'opens_at' => $start->toIso8601String()];
            }

            // Periods that run straight into each other are one stretch of being open
            if ($closes !== null && $start > $closes) {
                break;
            }

            $closes = $closes === null || $end > $closes ? $end : $closes;

            if ($closes->diffInDays($now, true) > self::ALWAYS_OPEN_DAYS) {
                return [...$status, 'open_now' => true];
            }
        }

        return [...$status, 'open_now' => $closes !== null, 'closes_at' => $closes?->toIso8601String()];
    }

    /**
     * The week's hours, Monday first, including days with none
     *
     * @return list<array{day_of_week: int, periods: list<Period>}>
     */
    public function weeklyHours(OrganisationUnit $location): array
    {
        return array_map(fn (int $day) => [
            'day_of_week' => $day,
            'periods' => array_values($location->openingHours
                ->where('day_of_week', $day)
                ->sortBy('opens_at')
                ->map(fn (OpeningHour $hour) => $this->period($hour->only(['opens_at', 'closes_at', 'label'])))
                ->all()),
        ], range(1, 7));
    }

    /**
     * The exceptions that haven't finished yet, soonest first
     *
     * @return list<array{name: string, starts_on: string, ends_on: string, closed: bool, periods: list<Period>}>
     */
    public function upcomingExceptions(OrganisationUnit $location, ?CarbonInterface $at = null): array
    {
        $today = CarbonImmutable::instance($at ?? now())->setTimezone($location->localTimezone())->toDateString();

        return array_values($location->openingHourExceptions
            ->filter(fn (OpeningHourException $exception) => $exception->ends_on->toDateString() >= $today)
            ->sortBy(fn (OpeningHourException $exception) => $exception->starts_on->toDateString())
            ->map(fn (OpeningHourException $exception) => [
                'name' => $exception->name,
                'starts_on' => $exception->starts_on->toDateString(),
                'ends_on' => $exception->ends_on->toDateString(),
                'closed' => $exception->isClosed(),
                'periods' => array_map($this->period(...), $exception->periods ?? []),
            ])
            ->all());
    }

    /**
     * The hours as schema.org OpeningHoursSpecification, ready to add to a business's JSON-LD. By
     * appointment there's nothing to add, as those hours don't mean someone can turn up.
     *
     * @return array{openingHoursSpecification: list<array<string, mixed>>, specialOpeningHoursSpecification: list<array<string, mixed>>}
     */
    public function schemaOrg(OrganisationUnit $location, ?CarbonInterface $at = null): array
    {
        if (! ($location->location_type?->canBeOpenNow() ?? true)) {
            return ['openingHoursSpecification' => [], 'specialOpeningHoursSpecification' => []];
        }

        $regular = [];

        $byTimes = $location->openingHours
            ->sortBy(['day_of_week', 'opens_at'])
            ->groupBy(fn (OpeningHour $hour) => "{$hour->opens_at}-{$hour->closes_at}");

        foreach ($byTimes as $hours) {
            $first = $hours->firstOrFail();

            $regular[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $hours->map(fn (OpeningHour $hour) => self::SCHEMA_ORG_DAYS[$hour->day_of_week])->unique()->values()->all(),
                'opens' => $first->opens_at,
                'closes' => $this->schemaOrgClose($first->opens_at, $first->closes_at),
            ];
        }

        $special = [];

        foreach ($this->upcomingExceptions($location, $at) as $exception) {
            $dates = [
                '@type' => 'OpeningHoursSpecification',
                'validFrom' => $exception['starts_on'],
                'validThrough' => $exception['ends_on'],
            ];

            // Closed is written as opening and closing at the same moment
            if ($exception['closed']) {
                $special[] = [...$dates, 'opens' => '00:00', 'closes' => '00:00'];
            }

            foreach ($exception['periods'] as $period) {
                $special[] = [...$dates, 'opens' => $period['opens_at'], 'closes' => $this->schemaOrgClose($period['opens_at'], $period['closes_at'])];
            }
        }

        return ['openingHoursSpecification' => $regular, 'specialOpeningHoursSpecification' => $special];
    }

    /**
     * Each period from the given day on, in order of opening
     *
     * @return Generator<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function intervals(OrganisationUnit $location, CarbonImmutable $from): Generator
    {
        for ($offset = 0; $offset <= self::SEARCH_DAYS; $offset++) {
            $day = $from->addDays($offset);

            foreach ($this->periodsOn($location, $day) as $period) {
                $start = $day->setTimeFromTimeString($period['opens_at']);
                $end = $day->setTimeFromTimeString($period['closes_at']);

                yield [$start, $period['closes_at'] <= $period['opens_at'] ? $end->addDay() : $end];
            }
        }
    }

    protected function hasHours(OrganisationUnit $location): bool
    {
        return $location->openingHours->isNotEmpty()
            || $location->openingHourExceptions->contains(fn (OpeningHourException $exception) => ! $exception->isClosed());
    }

    protected function schemaOrgClose(string $opens, string $closes): string
    {
        return $opens === '00:00' && $closes === '00:00' ? self::SCHEMA_ORG_END_OF_DAY : $closes;
    }

    /**
     * @param  array<string, mixed>  $period
     * @return Period
     */
    protected function period(array $period): array
    {
        return [
            'opens_at' => (string) $period['opens_at'],
            'closes_at' => (string) $period['closes_at'],
            'label' => isset($period['label']) ? (string) $period['label'] : null,
        ];
    }
}

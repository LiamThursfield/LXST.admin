<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Shift;
use App\Models\Bookings\TimeOff;
use App\Models\Bookings\WorkingHour;
use App\Support\TimePeriods;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * When a resource works on a date: nothing on approved time off, otherwise that date's shifts,
 * otherwise that weekday's working hours. A period belongs to the day it starts on. The booking
 * engine (#178) is to build availability on this.
 *
 * Reads the resource's loaded relations, so a caller going through many dates or resources can load
 * only the range it needs.
 *
 * @phpstan-type Period array{starts_at: string, ends_at: string, note: string|null}
 */
class ResourceSchedule
{
    public const string SOURCE_WEEKLY = 'weekly';

    public const string SOURCE_ROTA = 'rota';

    public const string SOURCE_OFF = 'off';

    /**
     * @return array{source: string, periods: list<Period>}
     */
    public function on(BookableResource $resource, CarbonInterface $date): array
    {
        $day = $date->toDateString();

        $isOff = $resource->timeOff->contains(
            fn (TimeOff $timeOff) => $timeOff->status === TimeOffStatus::Approved && $timeOff->covers($day)
        );

        if ($isOff) {
            return ['source' => self::SOURCE_OFF, 'periods' => []];
        }

        $shifts = $resource->shifts->filter(fn (Shift $shift) => $shift->works_on->toDateString() === $day);

        if ($shifts->isNotEmpty()) {
            return [
                'source' => self::SOURCE_ROTA,
                'periods' => array_values($shifts->sortBy('starts_at')
                    ->map(fn (Shift $shift) => ['starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at, 'note' => $shift->note])
                    ->all()),
            ];
        }

        return [
            'source' => self::SOURCE_WEEKLY,
            'periods' => array_values($resource->workingHours
                ->where('day_of_week', $date->dayOfWeekIso)
                ->sortBy('starts_at')
                ->map(fn (WorkingHour $hour) => ['starts_at' => $hour->starts_at, 'ends_at' => $hour->ends_at, 'note' => null])
                ->all()),
        ];
    }

    /**
     * The minutes scheduled between two dates, both included
     */
    public function minutesBetween(BookableResource $resource, CarbonInterface $from, CarbonInterface $to): int
    {
        $minutes = 0;

        foreach (CarbonPeriod::create($from, $to) as $date) {
            foreach ($this->on($resource, $date)['periods'] as $period) {
                $minutes += TimePeriods::minutes($period['starts_at'], $period['ends_at']);
            }
        }

        return $minutes;
    }
}

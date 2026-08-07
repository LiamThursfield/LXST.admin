<?php

namespace App\Data\Tracker;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * A recurrence filter layered on top of a TaskDefinition's period. A candidate
 * date is only "active" when it satisfies every filter that is present; any
 * filter left null is ignored.
 *
 * Note: {@see self::$interval} is stored but NOT evaluated here — interval
 * recurrence needs an anchor date (the definition's active_from) and is
 * resolved during occurrence generation, not by this value object.
 */
class TaskSchedule extends Data
{
    /**
     * @param  int[]|null  $daysOfWeek  ISO-8601 day numbers, 1=Mon … 7=Sun
     * @param  int[]|null  $monthsOfYear  Month numbers, 1..12
     * @param  int[]|null  $daysOfMonth  Day-of-month numbers, 1..31
     */
    public function __construct(
        public ?array $daysOfWeek = null,
        public ?array $monthsOfYear = null,
        public ?array $daysOfMonth = null,
        public int $interval = 1,
    ) {}

    /**
     * Whether the given date satisfies every present filter. A schedule with no
     * filters set matches every date.
     */
    public function matches(CarbonInterface $date): bool
    {
        if ($this->daysOfWeek !== null && ! in_array($date->isoWeekday(), $this->daysOfWeek, true)) {
            return false;
        }

        if ($this->monthsOfYear !== null && ! in_array($date->month, $this->monthsOfYear, true)) {
            return false;
        }

        if ($this->daysOfMonth !== null && ! in_array($date->day, $this->daysOfMonth, true)) {
            return false;
        }

        return true;
    }
}

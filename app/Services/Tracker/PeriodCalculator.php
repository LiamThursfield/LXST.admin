<?php

namespace App\Services\Tracker;

use App\Enums\Tracker\TrackerPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Pure calendar maths for the recurring periods (Daily, Weekly, Monthly,
 * Yearly). Weeks are ISO-8601 (Monday–Sunday) and the week key uses the ISO
 * week-numbering year, so the last days of December can belong to week 53 of
 * the same year and the first days of January to week 52/53 of the previous
 * year. Lifetime and OneOff are single, definition-bound windows and are
 * handled by the generator, not here.
 */
class PeriodCalculator
{
    /**
     * The window of the given recurring period that contains $date.
     */
    public function window(TrackerPeriod $period, CarbonImmutable $date): PeriodWindow
    {
        $date = $date->startOfDay();

        return match ($period) {
            TrackerPeriod::Daily => new PeriodWindow(
                $date->format('Y-m-d'),
                $date,
                $date,
            ),
            TrackerPeriod::Weekly => new PeriodWindow(
                // ISO week-numbering year + week number, e.g. 2026-W53.
                $date->format('o-\WW'),
                $date->startOfWeek(CarbonInterface::MONDAY),
                $date->endOfWeek(CarbonInterface::SUNDAY),
            ),
            TrackerPeriod::Monthly => new PeriodWindow(
                $date->format('Y-m'),
                $date->startOfMonth(),
                $date->endOfMonth(),
            ),
            TrackerPeriod::Yearly => new PeriodWindow(
                $date->format('Y'),
                $date->startOfYear(),
                $date->endOfYear(),
            ),
            default => throw new InvalidArgumentException(
                "PeriodCalculator does not handle the {$period->value} period.",
            ),
        };
    }

    /**
     * The start of the window that follows the one beginning at $windowStart.
     */
    public function next(TrackerPeriod $period, CarbonImmutable $windowStart): CarbonImmutable
    {
        return match ($period) {
            TrackerPeriod::Daily => $windowStart->addDay(),
            TrackerPeriod::Weekly => $windowStart->addWeek(),
            TrackerPeriod::Monthly => $windowStart->addMonth(),
            TrackerPeriod::Yearly => $windowStart->addYear(),
            default => throw new InvalidArgumentException(
                "PeriodCalculator does not handle the {$period->value} period.",
            ),
        };
    }

    /**
     * Whether the period produces repeating windows (as opposed to the single
     * lifetime / one-off window).
     */
    public function isRecurring(TrackerPeriod $period): bool
    {
        return in_array($period, [
            TrackerPeriod::Daily,
            TrackerPeriod::Weekly,
            TrackerPeriod::Monthly,
            TrackerPeriod::Yearly,
        ], true);
    }
}

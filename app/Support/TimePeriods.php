<?php

namespace App\Support;

/**
 * Wall clock periods as HH:MM, where an end at or before the start runs into the next day and
 * 00:00 to 00:00 is the whole day
 */
class TimePeriods
{
    public const string SAME_TIME = 'same_time';

    public const string OVERLAP = 'periods_overlap';

    /**
     * What's wrong with one day's periods, if anything: SAME_TIME or OVERLAP
     *
     * @param  array<mixed>  $periods
     */
    public static function problem(array $periods, string $startKey = 'opens_at', string $endKey = 'closes_at'): ?string
    {
        $times = collect($periods)
            ->filter(fn (mixed $period) => is_array($period) && is_string($period[$startKey] ?? null) && is_string($period[$endKey] ?? null))
            ->sortBy($startKey)
            ->values();

        foreach ($times as $index => $period) {
            if ($period[$startKey] === $period[$endKey] && $period[$startKey] !== '00:00') {
                return self::SAME_TIME;
            }

            $next = $times->get($index + 1);
            $runsPastMidnight = $period[$endKey] <= $period[$startKey];

            if ($next !== null && ($runsPastMidnight || $next[$startKey] < $period[$endKey])) {
                return self::OVERLAP;
            }
        }

        return null;
    }

    /**
     * How long a period lasts, in minutes
     */
    public static function minutes(string $start, string $end): int
    {
        $minutes = self::minutesIntoDay($end) - self::minutesIntoDay($start);

        return $minutes > 0 ? $minutes : $minutes + 1440;
    }

    protected static function minutesIntoDay(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }
}

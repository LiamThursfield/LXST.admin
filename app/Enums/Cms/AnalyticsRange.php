<?php

namespace App\Enums\Cms;

use App\Enums\Traits\HasLabels;
use Carbon\CarbonImmutable;

/**
 * The date ranges the dashboard's page view stats can show
 */
enum AnalyticsRange: string
{
    use HasLabels;

    case Days7 = '7d';
    case Days30 = '30d';
    case Days90 = '90d';
    case Months12 = '12m';

    /**
     * The first moment in the range: the start of the first day (or month), counting today
     */
    public function start(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::Days7 => $now->subDays(6)->startOfDay(),
            self::Days30 => $now->subDays(29)->startOfDay(),
            self::Days90 => $now->subDays(89)->startOfDay(),
            self::Months12 => $now->subMonthsNoOverflow(11)->startOfMonth(),
        };
    }

    /**
     * Whether views are counted per month rather than per day
     */
    public function isMonthly(): bool
    {
        return $this === self::Months12;
    }
}

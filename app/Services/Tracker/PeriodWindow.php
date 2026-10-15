<?php

namespace App\Services\Tracker;

use Carbon\CarbonImmutable;

/**
 * A single evaluation window for a task: its canonical key and date bounds.
 * The end is null only for an open-ended lifetime goal.
 */
final class PeriodWindow
{
    public function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
    ) {}
}

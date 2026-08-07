<?php

namespace App\Enums\Tracker;

use App\Enums\Traits\HasLabels;

enum TrackerComparator: string
{
    use HasLabels;

    case None = 'none';
    case Boolean = 'boolean';
    case Min = 'min';
    case Max = 'max';
    case Between = 'between';

    /**
     * Whether this comparator needs a lower-bound target (target_min).
     */
    public function requiresMin(): bool
    {
        return match ($this) {
            self::Min, self::Between => true,
            default => false,
        };
    }

    /**
     * Whether this comparator needs an upper-bound target (target_max).
     */
    public function requiresMax(): bool
    {
        return match ($this) {
            self::Max, self::Between => true,
            default => false,
        };
    }
}

<?php

namespace App\Enums\Tracker;

use App\Enums\Traits\HasLabels;

enum TrackerMeasure: string
{
    use HasLabels;

    case Boolean = 'boolean';
    case Count = 'count';
    case Weight = 'weight';
    case Volume = 'volume';
    case Duration = 'duration';
    case Energy = 'energy';
    case ClockTime = 'clock-time';
}

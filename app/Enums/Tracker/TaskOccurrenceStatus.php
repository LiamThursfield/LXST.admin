<?php

namespace App\Enums\Tracker;

use App\Enums\Traits\HasLabels;

enum TaskOccurrenceStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case OnTrack = 'on-track';
    case Met = 'met';
    case Missed = 'missed';
    case Skipped = 'skipped';
}

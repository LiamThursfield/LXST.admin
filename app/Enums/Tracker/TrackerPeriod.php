<?php

namespace App\Enums\Tracker;

use App\Enums\Traits\HasLabels;

enum TrackerPeriod: string
{
    use HasLabels;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';
    case OneOff = 'one-off';
}

<?php

namespace App\Enums\Tracker;

use App\Enums\Traits\HasLabels;

enum TrackerAggregation: string
{
    use HasLabels;

    case Sum = 'sum';
    case Last = 'last';
    case Min = 'min';
    case Max = 'max';
    case Count = 'count';
    case Any = 'any';
}

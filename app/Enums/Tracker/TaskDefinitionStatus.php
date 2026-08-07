<?php

namespace App\Enums\Tracker;

use App\Enums\Traits\HasLabels;

enum TaskDefinitionStatus: string
{
    use HasLabels;

    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}

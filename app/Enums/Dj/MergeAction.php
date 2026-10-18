<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * What happened to canonical records: two became one, a library track got its own, or library
 * records were assigned to one by hand
 */
enum MergeAction: string
{
    use HasLabels;

    case Merge = 'merge';
    case Split = 'split';
    case Assign = 'assign';
}

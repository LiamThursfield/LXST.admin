<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * What happened to canonical records: two became one, or a library track got its own
 */
enum MergeAction: string
{
    use HasLabels;

    case Merge = 'merge';
    case Split = 'split';
}

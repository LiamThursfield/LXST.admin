<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * The kinds of canonical record that can be merged
 */
enum CanonicalRecord: string
{
    use HasLabels;

    case Artist = 'artist';
    case Track = 'track';
}

<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * What a song on a setlist is for, as event planners ask for them
 */
enum SetlistSongType: string
{
    use HasLabels;

    case MustPlay = 'must_play';
    case Maybe = 'maybe';
    case DoNotPlay = 'do_not_play';
}

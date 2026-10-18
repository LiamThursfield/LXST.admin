<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * Setlists and a gig's requests share one model: lists of songs
 */
enum SongListKind: string
{
    use HasLabels;

    /** A planned list, e.g. a wedding's must plays */
    case Setlist = 'setlist';

    /** A gig's song requests (#120); a gig has at most one */
    case Requests = 'requests';
}

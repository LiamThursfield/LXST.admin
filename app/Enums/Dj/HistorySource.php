<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * Where a play history session came from
 */
enum HistorySource: string
{
    use HasLabels;

    /** A library import, e.g. Rekordbox's master.db */
    case Import = 'import';

    /** Plays added by hand to a gig, e.g. for gigs played on other gear */
    case Manual = 'manual';

    /** Plays reported live by the Rekordbox CLI tool during a gig (#124) */
    case Cli = 'cli';
}

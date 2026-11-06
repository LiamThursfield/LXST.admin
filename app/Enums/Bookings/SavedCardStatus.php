<?php

namespace App\Enums\Bookings;

use App\Enums\Traits\HasLabels;

/**
 * Where a card for later fees has got to: asked for, saved, or its link ran out
 */
enum SavedCardStatus: string
{
    use HasLabels;

    case Requested = 'requested';
    case Saved = 'saved';
    case Expired = 'expired';
}

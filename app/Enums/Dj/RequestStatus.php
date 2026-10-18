<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * Where a song request is in the DJ's queue (#122)
 */
enum RequestStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Played = 'played';

    /**
     * Statuses a new request for the same song becomes an upvote of
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Accepted];
    }
}

<?php

namespace App\Services\Dj\Requests;

use App\Models\Dj\GuestPass;
use App\Models\Dj\SongListItem;
use App\Services\Dj\Api\DjApiCache;

/**
 * Upvotes of song requests, one per visitor; the requester's own request counts as one
 */
class RequestVotes
{
    public function __construct(protected DjApiCache $cache) {}

    /**
     * Adds the visitor's vote. False when they'd already voted, or can't be told apart.
     */
    public function add(SongListItem $item, ?string $visitorHash, ?GuestPass $pass = null): bool
    {
        if ($visitorHash === null) {
            return false;
        }

        $added = $item->votes()->firstOrCreate(
            ['visitor_hash' => $visitorHash],
            ['guest_pass_id' => $pass?->id],
        )->wasRecentlyCreated;

        if ($added && $item->songList->gig !== null) {
            $this->cache->refreshRequests($item->songList->gig);
        }

        return $added;
    }
}

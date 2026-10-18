<?php

namespace App\Services\Dj\Requests;

use App\Models\Dj\SongListItem;
use App\Services\Dj\Matching\TrackNormaliser;

/**
 * A key for "the same song" on song lists: the same library song, or the same custom artist and
 * title once normalised (so "Daft Punk – One More Time" and "daft punk - one more time." match)
 */
class SongKeys
{
    public function __construct(protected TrackNormaliser $normaliser) {}

    public function key(?int $canonicalTrackId, ?string $customArtist, ?string $customTitle): string
    {
        if ($canonicalTrackId !== null) {
            return "track:{$canonicalTrackId}";
        }

        return 'custom:'.$this->normaliser->artistKey($customArtist).'|'.$this->normaliser->titleKey((string) $customTitle);
    }

    public function forItem(SongListItem $item): string
    {
        return $this->key($item->canonical_track_id, $item->custom_artist, $item->custom_title);
    }
}

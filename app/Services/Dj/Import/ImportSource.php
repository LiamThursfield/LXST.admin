<?php

namespace App\Services\Dj\Import;

use App\Services\Dj\Import\Data\ArtistData;
use App\Services\Dj\Import\Data\HistorySessionData;
use App\Services\Dj\Import\Data\TagData;
use App\Services\Dj\Import\Data\TrackData;

/**
 * A file a library can be imported from, e.g. a Rekordbox master.db or XML export.
 * Each import is a full snapshot: tracks it leaves out are marked missing.
 */
interface ImportSource
{
    public function trackCount(): int;

    /**
     * @return iterable<ArtistData>
     */
    public function artists(): iterable;

    /**
     * @return iterable<TagData>
     */
    public function tags(): iterable;

    /**
     * @return iterable<list<TrackData>>
     */
    public function trackChunks(int $size): iterable;

    /**
     * @return iterable<HistorySessionData>
     */
    public function historySessions(): iterable;
}

<?php

namespace App\Services\Dj\Import\Data;

use Carbon\CarbonImmutable;

/**
 * A play history session read from an import source, with its plays in order
 */
final readonly class HistorySessionData
{
    /**
     * @param  list<array{source_track_id: string, position: int, played_at: CarbonImmutable|null}>  $plays
     */
    public function __construct(
        public string $sourceHistoryId,
        public string $name,
        public array $plays,
    ) {}
}

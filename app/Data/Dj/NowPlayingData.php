<?php

namespace App\Data\Dj;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * A track the CLI tool reported as played (#124)
 */
class NowPlayingData extends Data
{
    public function __construct(
        public ?string $source_track_id,
        public ?int $library_id,
        public ?string $artist,
        public string $title,
        public CarbonImmutable $played_at,
    ) {}
}

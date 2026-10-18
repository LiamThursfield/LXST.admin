<?php

namespace App\Services\Dj\Import\Data;

use App\Enums\Dj\MusicalKey;
use Carbon\CarbonImmutable;

/**
 * A track read from an import source, already in this app's units (BPM as a decimal,
 * duration in seconds, rating as 0 to 5 stars)
 */
final readonly class TrackData
{
    /**
     * @param  list<string>  $sourceTagIds
     */
    public function __construct(
        public string $sourceTrackId,
        public string $title,
        public ?string $sourceArtistId = null,
        public ?string $album = null,
        public ?string $genre = null,
        public ?string $label = null,
        public ?int $year = null,
        public ?float $bpm = null,
        public ?int $duration = null,
        public ?MusicalKey $key = null,
        public ?string $comments = null,
        public int $playCount = 0,
        public ?int $rating = null,
        public ?CarbonImmutable $dateAdded = null,
        public ?string $location = null,
        public array $sourceTagIds = [],
    ) {}
}

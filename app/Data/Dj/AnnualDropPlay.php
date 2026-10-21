<?php

namespace App\Data\Dj;

use Carbon\CarbonImmutable;

/**
 * One play, as The Annual Drop (#127) counts it: what it was, by whom, its BPM and key
 */
final readonly class AnnualDropPlay
{
    public function __construct(
        /** The same song counts once: the canonical track, or else its normalised name */
        public string $songKey,
        public ?string $title,
        /** The same artist counts once: the canonical artist, or else their normalised name */
        public ?string $artistKey,
        public ?string $artistName,
        public ?float $bpm,
        public ?string $key,
        public ?int $gigId,
        public int $sessionId,
        public CarbonImmutable $playedAt,
    ) {}
}

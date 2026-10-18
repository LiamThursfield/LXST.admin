<?php

namespace App\Services\Dj\Import\Data;

/**
 * An artist read from an import source
 */
final readonly class ArtistData
{
    public function __construct(
        public string $sourceArtistId,
        public string $name,
    ) {}
}

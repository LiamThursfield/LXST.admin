<?php

namespace App\Services\Dj\Import\Data;

/**
 * A My Tag read from an import source
 */
final readonly class TagData
{
    public function __construct(
        public string $sourceTagId,
        public string $name,
        public ?string $category = null,
    ) {}
}

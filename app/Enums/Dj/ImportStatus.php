<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * Where a library import has got to
 */
enum ImportStatus: string
{
    use HasLabels;

    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Whether the import is still going, so the page keeps checking on it
     */
    public function isRunning(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}

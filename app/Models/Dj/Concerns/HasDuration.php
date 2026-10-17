<?php

namespace App\Models\Dj\Concerns;

/**
 * For tracks, whose duration is stored in seconds
 *
 * @property int|null $duration
 */
trait HasDuration
{
    /**
     * The duration as minutes and seconds, e.g. 3:45, or null when it isn't known
     */
    public function formattedDuration(): ?string
    {
        if ($this->duration === null) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($this->duration, 60), $this->duration % 60);
    }
}

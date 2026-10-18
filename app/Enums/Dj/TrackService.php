<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * The streaming service a library track plays from, read from its location
 * (e.g. tidal:tracks:356883832). Local files have none.
 */
enum TrackService: string
{
    use HasLabels;

    case Tidal = 'tidal';
    case Spotify = 'spotify';
    case Beatport = 'beatport';
    case SoundCloud = 'soundcloud';

    /**
     * The service and its track ID, from a Rekordbox location, or null for a local file
     *
     * @return array{0: self, 1: string}|null
     */
    public static function parseLocation(?string $location): ?array
    {
        // Rekordbox XML prefixes streaming IDs with file://localhost, e.g. file://localhosttidal:tracks:356883832
        $location = preg_replace('#^file://localhost/?#', '', trim((string) $location));

        if (! preg_match('/^([a-z]+):tracks?:([^:\/\s]+)$/i', (string) $location, $matches)) {
            return null;
        }

        $service = self::tryFrom(strtolower($matches[1]));

        return $service === null ? null : [$service, $matches[2]];
    }
}

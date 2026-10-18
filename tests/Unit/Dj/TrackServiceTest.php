<?php

use App\Enums\Dj\TrackService;

test('streaming services and their track IDs are read from Rekordbox locations', function (?string $location, ?array $expected) {
    expect(TrackService::parseLocation($location))->toBe($expected);
})->with([
    'master.db Tidal' => ['tidal:tracks:356883832', [TrackService::Tidal, '356883832']],
    'XML Tidal' => ['file://localhosttidal:tracks:356883832', [TrackService::Tidal, '356883832']],
    'Spotify' => ['spotify:track:4uLU6hMCjMI75M1A2tKUQC', [TrackService::Spotify, '4uLU6hMCjMI75M1A2tKUQC']],
    'local file' => ['/Users/dj/Music/Glue.mp3', null],
    'XML local file' => ['file://localhost/Users/dj/Music/Glue%20Edit.mp3', null],
    'unknown service' => ['deezer:tracks:123', null],
    'empty' => [null, null],
]);

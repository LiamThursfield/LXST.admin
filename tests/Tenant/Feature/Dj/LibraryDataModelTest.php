<?php

use App\Enums\Dj\LibrarySource;
use App\Enums\Dj\MusicalKey;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use Illuminate\Database\UniqueConstraintViolationException;

test('a library\'s tracks link to their artist and canonical track', function () {
    $library = Library::factory()->source(LibrarySource::CliSync)->create();
    $canonicalTrack = CanonicalTrack::factory()->create();
    $artist = LibraryArtist::factory()->for($library)->create(['canonical_artist_id' => $canonicalTrack->canonical_artist_id]);
    $track = LibraryTrack::factory()->for($library)->create([
        'library_artist_id' => $artist->id,
        'canonical_track_id' => $canonicalTrack->id,
    ]);

    expect($library->fresh()->source)->toBe(LibrarySource::CliSync)
        ->and($library->tracks->sole()->is($track))->toBeTrue()
        ->and($library->artists->sole()->is($artist))->toBeTrue()
        ->and($track->artist->is($artist))->toBeTrue()
        ->and($artist->tracks->sole()->is($track))->toBeTrue()
        ->and($track->canonicalTrack->is($canonicalTrack))->toBeTrue()
        ->and($canonicalTrack->libraryTracks->sole()->is($track))->toBeTrue()
        ->and($artist->canonicalArtist->is($canonicalTrack->artist))->toBeTrue()
        ->and($canonicalTrack->artist->tracks->sole()->is($canonicalTrack))->toBeTrue();
});

test('the same song in two libraries shares one canonical track', function () {
    $canonicalTrack = CanonicalTrack::factory()->create();

    LibraryTrack::factory()->count(2)->create(['canonical_track_id' => $canonicalTrack->id]);

    expect($canonicalTrack->libraryTracks()->count())->toBe(2)
        ->and(Library::count())->toBe(2);
});

test('a source ID is unique within a library, but may repeat across libraries', function (string $model) {
    $library = Library::factory()->create();
    $column = $model === LibraryTrack::class ? 'source_track_id' : 'source_artist_id';

    $model::factory()->for($library)->create([$column => '42']);
    $model::factory()->create([$column => '42']);

    expect(fn () => $model::factory()->for($library)->create([$column => '42']))
        ->toThrow(UniqueConstraintViolationException::class);
})->with([
    'tracks' => LibraryTrack::class,
    'artists' => LibraryArtist::class,
]);

test('deleting a library deletes its artists and tracks, but keeps the canonical records', function () {
    $library = Library::factory()->create();
    $canonicalTrack = CanonicalTrack::factory()->create();
    $artist = LibraryArtist::factory()->for($library)->create(['canonical_artist_id' => $canonicalTrack->canonical_artist_id]);
    LibraryTrack::factory()->for($library)->create(['library_artist_id' => $artist->id, 'canonical_track_id' => $canonicalTrack->id]);

    $library->delete();

    expect(LibraryArtist::count())->toBe(0)
        ->and(LibraryTrack::count())->toBe(0)
        ->and($canonicalTrack->fresh())->not->toBeNull()
        ->and(CanonicalArtist::count())->toBe(1);
});

test('deleting canonical records unlinks the library records rather than deleting them', function () {
    $canonicalTrack = CanonicalTrack::factory()->create();
    $artist = LibraryArtist::factory()->create(['canonical_artist_id' => $canonicalTrack->canonical_artist_id]);
    $track = LibraryTrack::factory()->create(['canonical_track_id' => $canonicalTrack->id]);

    $canonicalTrack->artist->delete();

    expect($artist->fresh()->canonical_artist_id)->toBeNull()
        ->and($canonicalTrack->fresh()->canonical_artist_id)->toBeNull();

    $canonicalTrack->delete();

    expect($track->fresh()->canonical_track_id)->toBeNull();
});

test('a track\'s key is stored as its Camelot code and its duration shown as minutes and seconds', function () {
    $track = LibraryTrack::factory()->create(['key' => MusicalKey::fromNotation('F#m'), 'duration' => 225, 'bpm' => 124]);

    expect($track->fresh()->key)->toBe(MusicalKey::FSharpMinor)
        ->and($track->getRawOriginal('key'))->toBe('11A')
        ->and($track->fresh()->bpm)->toBe('124.00')
        ->and($track->formattedDuration())->toBe('3:45');

    $track->update(['key' => null, 'duration' => null]);

    expect($track->fresh()->key)->toBeNull()
        ->and($track->fresh()->formattedDuration())->toBeNull();
});

test('canonical records can be marked as verified', function () {
    expect(CanonicalArtist::factory()->create()->isVerified())->toBeFalse()
        ->and(CanonicalArtist::factory()->verified()->create()->isVerified())->toBeTrue()
        ->and(CanonicalTrack::factory()->verified()->create()->isVerified())->toBeTrue();
});

test('library sources have labels', function () {
    expect(LibrarySource::RekordboxXml->label())->toBe('Rekordbox XML')
        ->and(LibrarySource::RekordboxDb->label())->toBe('Rekordbox database')
        ->and(LibrarySource::CliSync->label())->toBe('Rekordbox CLI sync');
});

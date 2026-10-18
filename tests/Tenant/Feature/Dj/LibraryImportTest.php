<?php

use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Enums\Dj\MusicalKey;
use App\Enums\Dj\TrackService;
use App\Jobs\Dj\ImportLibrary;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\HistorySession;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryImport;
use App\Models\Dj\LibraryTrack;
use App\Services\Dj\Import\Exceptions\InvalidImportFile;
use Illuminate\Support\Facades\Storage;
use Tests\Traits\BuildsRekordboxDatabasesTrait;

uses(BuildsRekordboxDatabasesTrait::class);

beforeEach(function () {
    Storage::fake(config('dj.imports.disk'));
    $this->library = Library::factory()->source(LibrarySource::RekordboxDb)->create();
});

/**
 * Uploads the database and runs its import straight away, as the queue would
 *
 * @param  array<string, mixed>  $data
 */
function runImport(mixed $test, array $data, ?Library $library = null): LibraryImport
{
    $path = 'dj-imports/'.uniqid().'.db';
    Storage::disk(config('dj.imports.disk'))->put($path, (string) file_get_contents($test->makeRekordboxDatabase($data)));

    $import = LibraryImport::factory()->for($library ?? $test->library)->create(['file_path' => $path]);

    ImportLibrary::dispatchSync($import);

    return $import->fresh();
}

test('a master.db import brings in artists, tracks with their details, tags and history', function () {
    $import = runImport($this, $this->sampleRekordboxLibrary());

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total)->toBe(2)
        ->and($import->processed)->toBe(2)
        ->and($import->progress())->toBe(100)
        ->and($import->counts)->toMatchArray([
            'artists_added' => 2,
            'tags' => 1,
            'tracks_added' => 2,
            'tracks_missing' => 0,
            'history_sessions' => 1,
            'history_plays' => 2,
        ]);

    // The track Rekordbox had deleted is left out
    expect($this->library->tracks()->count())->toBe(2);

    $track = $this->library->tracks()->where('source_track_id', '101')->sole();

    expect($track->title)->toBe('Marea')
        ->and($track->artist->name)->toBe('Fred again..')
        ->and($track->album)->toBe('Actual Life')
        ->and($track->genre)->toBe('House')
        ->and($track->label)->toBe('Atlantic')
        ->and($track->year)->toBe(2021)
        ->and($track->bpm)->toBe('124.00')
        ->and($track->duration)->toBe(225)
        ->and($track->key)->toBe(MusicalKey::AMinor)
        ->and($track->comments)->toBe('/* Peak */')
        ->and($track->play_count)->toBe(7)
        ->and($track->rating)->toBe(4)
        ->and($track->date_added->toDateString())->toBe('2024-12-25')
        ->and($track->service)->toBe(TrackService::Tidal)
        ->and($track->service_track_id)->toBe('356883832')
        ->and($track->tags->pluck('name')->all())->toBe(['Peak'])
        ->and($track->tags->first()->category)->toBe('Mood');

    $glue = $this->library->tracks()->where('source_track_id', '102')->sole();

    expect($glue->key)->toBe(MusicalKey::FSharpMinor)
        ->and($glue->service)->toBeNull()
        ->and($glue->location)->toBe('/Users/dj/Music/Glue.mp3');
});

test('imported records are linked to canonical artists and tracks', function () {
    runImport($this, $this->sampleRekordboxLibrary());

    $track = $this->library->tracks()->where('source_track_id', '101')->sole();

    expect($track->canonicalTrack->title)->toBe('Marea')
        ->and($track->canonicalTrack->artist->name)->toBe('Fred again..')
        ->and($track->artist->canonicalArtist->is($track->canonicalTrack->artist))->toBeTrue()
        ->and($track->canonicalTrack->key)->toBe(MusicalKey::AMinor);
});

test('the same song in two libraries shares one canonical track', function () {
    runImport($this, $this->sampleRekordboxLibrary());
    runImport($this, $this->sampleRekordboxLibrary(), Library::factory()->source(LibrarySource::RekordboxDb)->create());

    expect(LibraryTrack::count())->toBe(4)
        ->and(CanonicalTrack::count())->toBe(2)
        ->and(CanonicalArtist::count())->toBe(2);
});

test('history is kept with each play\'s time in UTC, in order, linked to its track', function () {
    runImport($this, $this->sampleRekordboxLibrary());

    $session = HistorySession::sole();
    $plays = $session->plays;

    expect($session->name)->toBe('HISTORY 2025-09-26')
        ->and($session->source_history_id)->toBe('701')
        ->and($session->started_at->toIso8601String())->toBe('2025-09-26T17:59:16+00:00')
        ->and($session->ended_at->toIso8601String())->toBe('2025-09-26T18:05:00+00:00')
        ->and($plays->pluck('position')->all())->toBe([1, 2])
        ->and($plays->first()->track->title)->toBe('Marea')
        ->and($plays->last()->source_track_id)->toBe('102');
});

test('re-importing updates rather than duplicates, and reports what changed', function () {
    runImport($this, $this->sampleRekordboxLibrary());

    $unchanged = runImport($this, $this->sampleRekordboxLibrary());

    expect($unchanged->counts)->toMatchArray(['tracks_added' => 0, 'tracks_updated' => 0, 'tracks_unchanged' => 2])
        ->and(LibraryTrack::count())->toBe(2)
        ->and(HistorySession::sole()->plays()->count())->toBe(2);

    $library = $this->sampleRekordboxLibrary();
    $library['tracks'][1]['Title'] = 'Glue (Edit)';

    $updated = runImport($this, $library);

    expect($updated->counts)->toMatchArray(['tracks_updated' => 1, 'tracks_unchanged' => 1])
        ->and($this->library->tracks()->where('source_track_id', '102')->value('title'))->toBe('Glue (Edit)');
});

test('re-importing keeps a track\'s canonical link, even if its title changes', function () {
    runImport($this, $this->sampleRekordboxLibrary());
    $canonicalId = $this->library->tracks()->where('source_track_id', '102')->value('canonical_track_id');

    $library = $this->sampleRekordboxLibrary();
    $library['tracks'][1]['Title'] = 'Glue (Edit)';
    runImport($this, $library);

    expect($this->library->tracks()->where('source_track_id', '102')->value('canonical_track_id'))->toBe($canonicalId);
});

test('tracks an import leaves out are marked missing, and cleared when they come back', function () {
    runImport($this, $this->sampleRekordboxLibrary());

    $library = $this->sampleRekordboxLibrary();
    unset($library['tracks'][1]);
    $library['tracks'] = array_values($library['tracks']);

    $import = runImport($this, $library);
    $glue = $this->library->tracks()->where('source_track_id', '102')->sole();

    expect($import->counts['tracks_missing'])->toBe(1)
        ->and($glue->missing_since)->not->toBeNull()
        ->and($this->library->tracks()->present()->count())->toBe(1);

    runImport($this, $this->sampleRekordboxLibrary());

    expect($glue->fresh()->missing_since)->toBeNull();
});

test('a file that cannot be imported fails the import with a reason, and is deleted', function (array $data, string $error) {
    expect(fn () => runImport($this, $data))->toThrow(InvalidImportFile::class);

    $import = LibraryImport::sole();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->error)->toBe(__($error))
        ->and($import->file_path)->toBeNull()
        ->and(Storage::disk(config('dj.imports.disk'))->allFiles('dj-imports'))->toBe([]);
})->with([
    'an older Rekordbox' => [['version' => '5000'], 'dj.imports.errors.unsupported_version'],
]);

test('an encrypted or unrelated file fails the import with a reason', function (string $contents, string $error) {
    $path = 'dj-imports/encrypted.db';
    Storage::disk(config('dj.imports.disk'))->put($path, $contents);
    $import = LibraryImport::factory()->for($this->library)->create(['file_path' => $path]);

    expect(fn () => ImportLibrary::dispatchSync($import))->toThrow(InvalidImportFile::class);

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->error)->toBe(__($error));
})->with([
    'encrypted' => [random_bytes(4096), 'dj.imports.errors.not_decrypted'],
    'another SQLite database' => [(string) file_get_contents((function () {
        $path = (string) tempnam(sys_get_temp_dir(), 'other-');
        (new PDO('sqlite:'.$path))->exec('create table something (id integer)');

        return $path;
    })()), 'dj.imports.errors.not_rekordbox'],
]);

test('deleting a library deletes its tracks, tags, history and imports, but keeps canonical records', function () {
    runImport($this, $this->sampleRekordboxLibrary());

    $this->library->delete();

    expect(LibraryTrack::count())->toBe(0)
        ->and(HistorySession::count())->toBe(0)
        ->and(LibraryImport::count())->toBe(0)
        ->and(CanonicalTrack::count())->toBe(2);
});

/**
 * Uploads an XML export and runs its import straight away, as the queue would
 *
 * @param  list<array<string, string|int>>  $tracks
 */
function runXmlImport(mixed $test, array $tracks, ?Library $library = null, string $product = 'rekordbox'): LibraryImport
{
    $path = 'dj-imports/'.uniqid().'.xml';
    Storage::disk(config('dj.imports.disk'))->put($path, (string) file_get_contents($test->makeRekordboxXml($tracks, $product)));

    $library ??= Library::factory()->source(LibrarySource::RekordboxXml)->create();
    $import = LibraryImport::factory()->for($library)->create(['source' => LibrarySource::RekordboxXml, 'file_path' => $path]);

    ImportLibrary::dispatchSync($import);

    return $import->fresh();
}

test('an XML import brings in tracks with their details, and artists by name', function () {
    $import = runXmlImport($this, $this->sampleRekordboxXmlTracks());
    $library = $import->library;

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->total)->toBe(2)
        ->and($import->counts)->toMatchArray([
            'artists_added' => 1,
            'tracks_added' => 2,
            'history_sessions' => 0,
            'tags' => 0,
        ])
        // The playlist's reference to the first track isn't a second track
        ->and($library->tracks()->count())->toBe(2);

    $marea = $library->tracks()->where('source_track_id', '101')->sole();

    expect($marea->title)->toBe('Marea')
        ->and($marea->artist->name)->toBe('Fred again..')
        ->and($marea->artist->source_artist_id)->toBe('fred again..')
        ->and($marea->album)->toBe('Actual Life')
        ->and($marea->genre)->toBe('House')
        ->and($marea->label)->toBe('Atlantic')
        ->and($marea->year)->toBe(2021)
        ->and($marea->bpm)->toBe('124.00')
        ->and($marea->duration)->toBe(225)
        ->and($marea->key)->toBe(MusicalKey::AMinor)
        ->and($marea->comments)->toBe('/* Peak */')
        ->and($marea->play_count)->toBe(7)
        ->and($marea->rating)->toBe(4)
        ->and($marea->date_added->toDateString())->toBe('2024-12-25')
        // Stored the way master.db has it
        ->and($marea->location)->toBe('tidal:tracks:356883832')
        ->and($marea->service)->toBe(TrackService::Tidal)
        ->and($marea->service_track_id)->toBe('356883832');

    $delilah = $library->tracks()->where('source_track_id', '102')->sole();

    // The same artist, whatever the case or spacing
    expect($delilah->library_artist_id)->toBe($marea->library_artist_id)
        ->and($delilah->key)->toBe(MusicalKey::FSharpMinor)
        ->and($delilah->bpm)->toBeNull()
        ->and($delilah->duration)->toBeNull()
        ->and($delilah->rating)->toBeNull()
        ->and($delilah->location)->toBe('/Users/dj/Music/Delilah (Edit).mp3')
        ->and($delilah->service)->toBeNull();
});

test('re-importing an XML export updates rather than duplicates, and marks what it left out', function () {
    $library = runXmlImport($this, $this->sampleRekordboxXmlTracks())->library;

    $unchanged = runXmlImport($this, $this->sampleRekordboxXmlTracks(), $library);

    expect($unchanged->counts)->toMatchArray(['tracks_added' => 0, 'tracks_unchanged' => 2]);

    $missing = runXmlImport($this, [$this->sampleRekordboxXmlTracks()[0]], $library);

    expect($missing->counts['tracks_missing'])->toBe(1)
        ->and($library->tracks()->present()->count())->toBe(1)
        ->and(LibraryTrack::count())->toBe(2);
});

test('the same song from a master.db and an XML export shares one canonical track', function () {
    runImport($this, $this->sampleRekordboxLibrary());
    runXmlImport($this, $this->sampleRekordboxXmlTracks());

    $canonicalIds = LibraryTrack::query()->where('title', 'Marea')->pluck('canonical_track_id')->unique();

    expect($canonicalIds)->toHaveCount(1);
});

test('an XML file that cannot be imported fails the import with a reason', function (string $contents, string $error) {
    $path = 'dj-imports/broken.xml';
    Storage::disk(config('dj.imports.disk'))->put($path, $contents);
    $import = LibraryImport::factory()->for(Library::factory()->source(LibrarySource::RekordboxXml))->create([
        'source' => LibrarySource::RekordboxXml,
        'file_path' => $path,
    ]);

    expect(fn () => ImportLibrary::dispatchSync($import))->toThrow(InvalidImportFile::class);

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->error)->toBe(__($error))
        ->and($import->fresh()->file_path)->toBeNull();
})->with([
    'another app' => ['<?xml version="1.0"?><DJ_PLAYLISTS><PRODUCT Name="Serato"/></DJ_PLAYLISTS>', 'dj.imports.errors.not_rekordbox_xml'],
    'not XML' => ['not xml at all', 'dj.imports.errors.not_rekordbox_xml'],
    'cut off part way' => ['<?xml version="1.0"?><DJ_PLAYLISTS Version="1.0.0"><PRODUCT Name="rekordbox"/><COLLECTION Entries="1"><TRACK TrackID="1" Name="A"', 'dj.imports.errors.malformed_xml'],
]);

test('an XML export with an external entity does not load it', function () {
    $secret = tempnam(sys_get_temp_dir(), 'secret-');
    file_put_contents($secret, 'top secret');

    $xml = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY leak SYSTEM "file://'.$secret.'">]>'
        .'<DJ_PLAYLISTS Version="1.0.0"><PRODUCT Name="rekordbox"/><COLLECTION Entries="1">'
        .'<TRACK TrackID="1" Name="&leak;"/></COLLECTION></DJ_PLAYLISTS>';
    $path = 'dj-imports/entity.xml';
    Storage::disk(config('dj.imports.disk'))->put($path, $xml);
    $import = LibraryImport::factory()->for(Library::factory()->source(LibrarySource::RekordboxXml))->create([
        'source' => LibrarySource::RekordboxXml,
        'file_path' => $path,
    ]);

    try {
        ImportLibrary::dispatchSync($import);
    } catch (InvalidImportFile) {
        // Refusing the file is fine too
    }

    expect(LibraryTrack::query()->where('title', 'like', '%top secret%')->exists())->toBeFalse();
});

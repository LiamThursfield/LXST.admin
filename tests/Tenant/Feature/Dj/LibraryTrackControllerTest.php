<?php

use App\Enums\Dj\MusicalKey;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTag;
use App\Models\Dj\LibraryTrack;
use App\Services\Authorisation\Enums\DjPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));
    $this->library = Library::factory()->create(['name' => 'Home']);
});

/**
 * The titles the tracks page lists for the given query
 *
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function listedTitles(mixed $test, array $query = []): array
{
    $titles = [];

    $test->get(route('admin.dj.tracks.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$titles) {
            $page->component('admin/dj/track/Index');
            $titles = array_column($page->toArray()['props']['tracks']['data'], 'title');
        });

    return $titles;
}

test('tracks are listed by title, with their artist, library and key', function () {
    $artist = LibraryArtist::factory()->for($this->library)->create(['name' => 'Bicep']);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Glue', 'library_artist_id' => $artist->id, 'key' => MusicalKey::FSharpMinor, 'bpm' => 129.5, 'duration' => 269]);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Atlas']);

    $this->get(route('admin.dj.tracks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tracks.data.0.title', 'Atlas')
            ->where('tracks.data.1.title', 'Glue')
            ->where('tracks.data.1.artist.name', 'Bicep')
            ->where('tracks.data.1.library', 'Home')
            ->where('tracks.data.1.bpm', 129.5)
            ->where('tracks.data.1.duration', '4:29')
            ->where('tracks.data.1.key.camelot', '11A')
            ->where('tracks.data.1.key.standard', 'F#m')
            ->where('tracks.meta.total', 2)
            ->has('keys', 24)
            ->where('keys.0.value', '1A')
            ->where('libraries.0.label', 'Home')
        );
});

test('tracks can be searched by title or artist', function () {
    $artist = LibraryArtist::factory()->for($this->library)->create(['name' => 'Fred again..']);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Marea', 'library_artist_id' => $artist->id]);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Glue']);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Fredrik']);

    expect(listedTitles($this, ['filter' => ['search' => 'fred']]))->toBe(['Fredrik', 'Marea'])
        ->and(listedTitles($this, ['filter' => ['search' => 'glu']]))->toBe(['Glue']);
});

test('tracks can be filtered by library, artist and genre', function () {
    $other = Library::factory()->create();
    $artist = LibraryArtist::factory()->for($this->library)->create();
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Home house', 'genre' => 'House', 'library_artist_id' => $artist->id]);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Home techno', 'genre' => 'Techno']);
    LibraryTrack::factory()->for($other)->create(['title' => 'Elsewhere', 'genre' => 'House']);

    expect(listedTitles($this, ['filter' => ['library_id' => $this->library->id]]))->toBe(['Home house', 'Home techno'])
        ->and(listedTitles($this, ['filter' => ['artist_id' => $artist->id]]))->toBe(['Home house'])
        ->and(listedTitles($this, ['filter' => ['genre' => 'House']]))->toBe(['Elsewhere', 'Home house']);
});

test('tracks can be filtered by key, optionally with the keys that mix well with it', function () {
    foreach (['8A' => 'A minor', '9A' => 'E minor', '8B' => 'C major', '3A' => 'B flat minor'] as $key => $title) {
        LibraryTrack::factory()->for($this->library)->create(['title' => $title, 'key' => MusicalKey::from($key)]);
    }

    expect(listedTitles($this, ['filter' => ['key' => '8A']]))->toBe(['A minor'])
        ->and(listedTitles($this, ['filter' => ['key' => '8A', 'compatible' => '1']]))->toBe(['A minor', 'C major', 'E minor']);
});

test('tracks can be filtered by BPM and length', function () {
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Slow short', 'bpm' => 90, 'duration' => 150]);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'House', 'bpm' => 124, 'duration' => 360]);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Drum and bass', 'bpm' => 174, 'duration' => 300]);

    expect(listedTitles($this, ['filter' => ['bpm_min' => 120, 'bpm_max' => 130]]))->toBe(['House'])
        ->and(listedTitles($this, ['filter' => ['duration_min' => 5]]))->toBe(['Drum and bass', 'House'])
        ->and(listedTitles($this, ['filter' => ['duration_max' => 3]]))->toBe(['Slow short']);
});

test('missing tracks are hidden unless asked for', function () {
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Here']);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Gone', 'missing_since' => now()]);

    expect(listedTitles($this))->toBe(['Here'])
        ->and(listedTitles($this, ['filter' => ['missing' => '1']]))->toBe(['Gone', 'Here']);
});

test('tracks can be sorted', function (string $sort, array $titles) {
    $a = LibraryArtist::factory()->for($this->library)->create(['name' => 'Zed']);
    $b = LibraryArtist::factory()->for($this->library)->create(['name' => 'Abba']);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'One', 'bpm' => 128, 'library_artist_id' => $a->id, 'play_count' => 1]);
    LibraryTrack::factory()->for($this->library)->create(['title' => 'Two', 'bpm' => 100, 'library_artist_id' => $b->id, 'play_count' => 9]);

    expect(listedTitles($this, ['sort' => $sort]))->toBe($titles);
})->with([
    'BPM' => ['bpm', ['Two', 'One']],
    'BPM, highest first' => ['-bpm', ['One', 'Two']],
    'artist' => ['artist', ['Two', 'One']],
    'play count, highest first' => ['-play_count', ['Two', 'One']],
]);

test('the track filters are validated', function (array $filter, string $error) {
    $this->get(route('admin.dj.tracks.index', ['filter' => $filter]))->assertSessionHasErrors($error);
})->with([
    'an unknown key' => [['key' => '13A'], 'filter.key'],
    'a BPM range the wrong way round' => [['bpm_min' => 130, 'bpm_max' => 120], 'filter.bpm_max'],
    'a library that does not exist' => [['library_id' => 999], 'filter.library_id'],
    'a negative length' => [['duration_min' => -1], 'filter.duration_min'],
]);

test('a track shows its details, canonical track, other libraries, tags and play history', function () {
    $canonical = CanonicalTrack::factory()->create(['title' => 'Glue']);
    $track = LibraryTrack::factory()->for($this->library)->create(['title' => 'Glue', 'canonical_track_id' => $canonical->id, 'album' => 'Bicep']);
    $other = LibraryTrack::factory()->for(Library::factory()->create(['name' => 'Laptop']))->create(['title' => 'Glue (Edit)', 'canonical_track_id' => $canonical->id]);
    $track->tags()->attach(LibraryTag::factory()->for($this->library)->create(['name' => 'Peak', 'category' => 'Mood']));
    $session = HistorySession::factory()->for($this->library)->create(['name' => 'HISTORY 2025-09-26']);
    HistoryPlay::factory()->for($session, 'session')->count(2)->create(['library_track_id' => $track->id]);

    $this->get(route('admin.dj.tracks.show', $track))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/track/Show')
            ->where('track.data.title', 'Glue')
            ->where('track.data.album', 'Bicep')
            ->where('track.data.canonical.title', 'Glue')
            ->has('track.data.other_sources', 1)
            ->where('track.data.other_sources.0.id', $other->id)
            ->where('track.data.other_sources.0.library', 'Laptop')
            ->where('track.data.tags.0.name', 'Peak')
            ->where('track.data.tags.0.category', 'Mood')
            ->where('track.data.history_plays_count', 2)
            ->where('track.data.history_plays.0.session', 'HISTORY 2025-09-26')
        );
});

test('artists are listed with their track counts, and link to their tracks', function () {
    $artist = LibraryArtist::factory()->for($this->library)->create(['name' => 'Bicep']);
    LibraryTrack::factory()->for($this->library)->count(2)->create(['library_artist_id' => $artist->id]);
    LibraryTrack::factory()->for($this->library)->create(['library_artist_id' => $artist->id, 'missing_since' => now()]);
    LibraryArtist::factory()->for($this->library)->create(['name' => 'Avalon Emerson']);

    $this->get(route('admin.dj.artists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/artist/Index')
            ->where('artists.data.0.name', 'Avalon Emerson')
            ->where('artists.data.1.name', 'Bicep')
            ->where('artists.data.1.tracks_count', 2)
            ->where('artists.data.1.tracks_url', route('admin.dj.tracks.index', ['filter' => ['artist_id' => $artist->id]]))
        );

    $this->get(route('admin.dj.artists.index', ['filter' => ['search' => 'bic'], 'sort' => '-tracks_count']))
        ->assertInertia(fn (Assert $page) => $page->has('artists.data', 1)->where('artists.data.0.name', 'Bicep'));
});

test('users without library permissions cannot browse the library', function () {
    $this->actingAs($this->createUserWithPermissions([]));
    $track = LibraryTrack::factory()->for($this->library)->create();

    $this->get(route('admin.dj.tracks.index'))->assertForbidden();
    $this->get(route('admin.dj.tracks.show', $track))->assertForbidden();
    $this->get(route('admin.dj.artists.index'))->assertForbidden();
});

test('the library is not found when the tenant does not have the DJ module', function () {
    $track = LibraryTrack::factory()->for($this->library)->create();
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.dj.tracks.index'))->assertNotFound();
    $this->get(route('admin.dj.tracks.show', $track))->assertNotFound();
    $this->get(route('admin.dj.artists.index'))->assertNotFound();
});

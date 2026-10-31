<?php

use App\Enums\Dj\MusicalKey;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use App\Services\Authorisation\Enums\DjPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary, DjPermission::ManageLibrary]));
});

/**
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function listedSongs(mixed $test, array $query = []): array
{
    $titles = [];

    $test->get(route('admin.dj.songs.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$titles) {
            $page->component('admin/dj/song/Index');
            $titles = array_column($page->toArray()['props']['songs']['data'], 'title');
        });

    return $titles;
}

test('songs are listed with their artist and how many libraries they are in', function () {
    $artist = CanonicalArtist::factory()->create(['name' => 'Bicep']);
    $glue = CanonicalTrack::factory()->create(['title' => 'Glue', 'canonical_artist_id' => $artist->id]);
    LibraryTrack::factory()->for(Library::factory())->create(['canonical_track_id' => $glue->id]);
    LibraryTrack::factory()->for(Library::factory())->create(['canonical_track_id' => $glue->id]);

    $this->get(route('admin.dj.songs.index', ['filter' => ['search' => 'bicep']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('songs.data', 1)
            ->where('songs.data.0.title', 'Glue')
            ->where('songs.data.0.artist.name', 'Bicep')
            ->where('songs.data.0.library_tracks_count', 2)
            ->where('songs.data.0.libraries_count', 2)
            ->where('songs.data.0.url', route('admin.dj.songs.show', $glue))
            ->where('canManage', true)
        );
});

test('songs can be filtered by verified, shared, orphaned, key and artist', function () {
    $artist = CanonicalArtist::factory()->create();
    $verified = CanonicalTrack::factory()->verified()->create(['title' => 'Verified', 'key' => MusicalKey::AMinor, 'canonical_artist_id' => $artist->id]);
    $shared = CanonicalTrack::factory()->create(['title' => 'Shared', 'key' => MusicalKey::CMajor]);
    $orphan = CanonicalTrack::factory()->create(['title' => 'Orphan', 'key' => MusicalKey::CMajor]);
    LibraryTrack::factory()->create(['canonical_track_id' => $verified->id]);
    LibraryTrack::factory()->create(['canonical_track_id' => $shared->id]);
    LibraryTrack::factory()->create(['canonical_track_id' => $shared->id]);
    LibraryTrack::factory()->create(['canonical_track_id' => $orphan->id, 'missing_since' => now()]);

    expect(listedSongs($this, ['filter' => ['verified' => 'yes']]))->toBe(['Verified'])
        ->and(listedSongs($this, ['filter' => ['verified' => 'no']]))->toBe(['Orphan', 'Shared'])
        ->and(listedSongs($this, ['filter' => ['shared' => '1']]))->toBe(['Shared'])
        ->and(listedSongs($this, ['filter' => ['orphaned' => '1']]))->toBe(['Orphan'])
        ->and(listedSongs($this, ['filter' => ['key' => '8B']]))->toBe(['Orphan', 'Shared'])
        ->and(listedSongs($this, ['filter' => ['artist_id' => $artist->id]]))->toBe(['Verified']);

    $this->get(route('admin.dj.songs.index'))->assertInertia(fn (Assert $page) => $page->where('orphanedCount', 1));
});

test('a song shows its library tracks, combined play history and merge history', function () {
    $song = CanonicalTrack::factory()->create(['title' => 'Glue']);
    $home = LibraryTrack::factory()->for(Library::factory()->create(['name' => 'Home']))->create(['canonical_track_id' => $song->id]);
    $laptop = LibraryTrack::factory()->for(Library::factory()->create(['name' => 'Laptop']))->create(['canonical_track_id' => $song->id]);
    HistoryPlay::factory()->create(['library_track_id' => $home->id]);
    HistoryPlay::factory()->create(['library_track_id' => $laptop->id]);
    CanonicalMerge::query()->create(['record' => 'track', 'action' => 'merge', 'target_id' => $song->id, 'target_name' => 'Glue', 'source_id' => 999, 'source_name' => 'Glu', 'moved_count' => 1]);
    CanonicalMerge::query()->create(['record' => 'track', 'action' => 'merge', 'target_id' => 555, 'target_name' => 'Other', 'source_id' => 556, 'source_name' => 'Another', 'moved_count' => 1]);

    $this->get(route('admin.dj.songs.show', $song))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/song/Show')
            ->where('song.title', 'Glue')
            ->has('libraryTracks.data', 2)
            ->where('playsCount', 2)
            ->has('merges.data', 1)
            ->where('merges.data.0.source_name', 'Glu')
        );
});

test('a song has an edit page with its details and the keys to choose from', function () {
    $artist = CanonicalArtist::factory()->create(['name' => 'Bicep']);
    $song = CanonicalTrack::factory()->create(['title' => 'Glue', 'canonical_artist_id' => $artist->id, 'key' => MusicalKey::FSharpMinor]);

    $this->get(route('admin.dj.songs.edit', $song))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/song/Edit')
            ->where('song.title', 'Glue')
            ->where('song.artist.name', 'Bicep')
            ->where('song.key.value', '11A')
            ->has('keys', count(MusicalKey::cases()))
        );
});

test('editing a song saves its details and verifies it', function () {
    $artist = CanonicalArtist::factory()->create();
    $song = CanonicalTrack::factory()->create(['title' => 'Glue (Dirty)']);

    $this->patch(route('admin.dj.songs.update', $song), [
        'title' => 'Glue',
        'canonical_artist_id' => $artist->id,
        'bpm' => 129.5,
        'key' => '11A',
        'duration' => '4:29',
    ])->assertRedirect(route('admin.dj.songs.show', $song))->assertSessionHas('success', __('dj.songs.flash.updated'));

    expect($song->fresh())
        ->title->toBe('Glue')
        ->match_key->toBe('glue')
        ->canonical_artist_id->toBe($artist->id)
        ->bpm->toBe('129.50')
        ->key->toBe(MusicalKey::FSharpMinor)
        ->duration->toBe(269)
        ->and($song->fresh()->isVerified())->toBeTrue();
});

test('song edits are validated', function (array $data, string $error) {
    $song = CanonicalTrack::factory()->create();

    $this->patch(route('admin.dj.songs.update', $song), ['title' => 'Glue', ...$data])->assertSessionHasErrors($error);
})->with([
    'no title' => [['title' => ''], 'title'],
    'a length that is not minutes and seconds' => [['duration' => '269'], 'duration'],
    'too many seconds' => [['duration' => '4:75'], 'duration'],
    'an unknown key' => [['key' => '13A'], 'key'],
    'an artist that does not exist' => [['canonical_artist_id' => 999], 'canonical_artist_id'],
]);

test('cleaning up deletes songs and artists nothing links to, but keeps verified ones', function () {
    $linked = CanonicalTrack::factory()->create();
    LibraryTrack::factory()->create(['canonical_track_id' => $linked->id]);
    $orphan = CanonicalTrack::factory()->create();
    $verifiedOrphan = CanonicalTrack::factory()->verified()->create();
    $artistInUse = LibraryArtist::factory()->create(['canonical_artist_id' => CanonicalArtist::factory()->create()->id])->canonicalArtist;
    $orphanArtist = CanonicalArtist::factory()->create();

    $this->post(route('admin.dj.songs.cleanup'))->assertRedirect();

    expect($linked->fresh())->not->toBeNull()
        ->and($orphan->fresh())->toBeNull()
        ->and($verifiedOrphan->fresh())->not->toBeNull()
        ->and($artistInUse->fresh())->not->toBeNull()
        ->and($orphanArtist->fresh())->toBeNull();
});

test('users who can only view the library can browse songs but not change them', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));
    $song = CanonicalTrack::factory()->create(['title' => 'Glue']);

    $this->get(route('admin.dj.songs.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->get(route('admin.dj.songs.show', $song))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->get(route('admin.dj.songs.edit', $song))->assertForbidden();
    $this->patch(route('admin.dj.songs.update', $song), ['title' => 'Changed'])->assertForbidden();
    $this->post(route('admin.dj.songs.cleanup'))->assertForbidden();

    expect($song->fresh()->title)->toBe('Glue');
});

test('songs are not found without library permissions or the DJ module', function () {
    $song = CanonicalTrack::factory()->create();

    $this->actingAs($this->createUserWithPermissions([]));
    $this->get(route('admin.dj.songs.index'))->assertForbidden();
    $this->get(route('admin.dj.songs.show', $song))->assertForbidden();

    tenant()->update(['modules' => [TenantModule::Cms]]);
    $this->get(route('admin.dj.songs.index'))->assertNotFound();
});

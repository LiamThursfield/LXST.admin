<?php

use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use App\Services\Authorisation\Enums\DjPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([DjPermission::ViewLibrary, DjPermission::ManageLibrary]);
    $this->actingAs($this->user);
});

/**
 * A song with a present track in the given library (a new one by default)
 *
 * @param  array<string, mixed>  $attributes
 */
function songInLibrary(?Library $library = null, array $attributes = []): CanonicalTrack
{
    $song = CanonicalTrack::factory()->create($attributes);
    LibraryTrack::factory()->for($library ?? Library::factory()->create())->create(['canonical_track_id' => $song->id]);

    return $song;
}

test('request search leaves out hidden songs, songs by hidden artists, and songs not in a shown library', function () {
    $requestable = songInLibrary();
    $hidden = songInLibrary(attributes: ['hidden_from_requests_at' => now()]);
    $byHiddenArtist = songInLibrary(attributes: ['canonical_artist_id' => CanonicalArtist::factory()->create(['hidden_from_requests_at' => now()])->id]);
    $withoutArtist = songInLibrary(attributes: ['canonical_artist_id' => null]);
    $orphan = CanonicalTrack::factory()->create();
    $missing = CanonicalTrack::factory()->create();
    LibraryTrack::factory()->create(['canonical_track_id' => $missing->id, 'missing_since' => now()]);
    $onlyInHiddenLibrary = songInLibrary(Library::factory()->create(['hidden_from_requests_at' => now()]));
    // Also in a shown library, so it's still requestable
    $alsoInShownLibrary = songInLibrary(Library::factory()->create(['hidden_from_requests_at' => now()]));
    LibraryTrack::factory()->create(['canonical_track_id' => $alsoInShownLibrary->id]);

    expect(CanonicalTrack::query()->requestable()->pluck('id')->sort()->values()->all())
        ->toBe([$requestable->id, $withoutArtist->id, $alsoInShownLibrary->id])
        ->and(CanonicalTrack::query()->hiddenFromRequests()->pluck('id')->sort()->values()->all())
        ->toBe([$hidden->id, $byHiddenArtist->id]);

    expect([$orphan, $missing, $onlyInHiddenLibrary])->each(fn ($song) => $song->id->not->toBeIn(
        CanonicalTrack::query()->requestable()->pluck('id')->all()
    ));
});

test('a song, an artist and a library can each be hidden from requests and shown again', function () {
    $song = CanonicalTrack::factory()->create();
    $artist = CanonicalArtist::factory()->create();
    $library = Library::factory()->create();

    $this->patch(route('admin.dj.canonical-tracks.hide', $song), ['hidden' => true])
        ->assertRedirect()->assertSessionHas('success', __('dj.requests_visibility.flash.song_hidden'));
    $this->patch(route('admin.dj.canonical-artists.hide', $artist), ['hidden' => true])->assertRedirect();
    $this->patch(route('admin.dj.libraries.hide', $library), ['hidden' => true])->assertRedirect();

    expect($song->fresh()->isHiddenFromRequests())->toBeTrue()
        ->and($artist->fresh()->isHiddenFromRequests())->toBeTrue()
        ->and($library->fresh()->isHiddenFromRequests())->toBeTrue();

    $this->patch(route('admin.dj.canonical-tracks.hide', $song), ['hidden' => false])
        ->assertSessionHas('success', __('dj.requests_visibility.flash.song_shown'));

    expect($song->fresh()->isHiddenFromRequests())->toBeFalse();

    $this->patch(route('admin.dj.libraries.hide', $library), ['hidden' => 'maybe'])->assertSessionHasErrors('hidden');
});

test('selected library tracks and artists hide their songs and canonical artists in bulk', function () {
    $song = CanonicalTrack::factory()->create();
    $tracks = LibraryTrack::factory()->count(2)->create(['canonical_track_id' => $song->id]);
    $unmatched = LibraryTrack::factory()->create();
    $canonicalArtist = CanonicalArtist::factory()->create();
    $artist = LibraryArtist::factory()->create(['canonical_artist_id' => $canonicalArtist->id]);

    $this->post(route('admin.dj.tracks.bulk.hide'), ['track_ids' => [...$tracks->modelKeys(), $unmatched->id], 'hidden' => true])
        ->assertSessionHas('success', trans_choice('dj.bulk.flash.songs_hidden', 1, ['count' => 1]));
    $this->post(route('admin.dj.artists.bulk.hide'), ['artist_ids' => [$artist->id], 'hidden' => true])->assertRedirect();

    expect($song->fresh()->isHiddenFromRequests())->toBeTrue()
        ->and($canonicalArtist->fresh()->isHiddenFromRequests())->toBeTrue();

    $this->post(route('admin.dj.artists.bulk.hide'), ['artist_ids' => [$artist->id], 'hidden' => false])->assertRedirect();

    expect($canonicalArtist->fresh()->isHiddenFromRequests())->toBeFalse();

    // Each bulk action only takes its own field
    $this->post(route('admin.dj.tracks.bulk.hide'), ['track_ids' => [$unmatched->id], 'verified' => true])
        ->assertSessionHasErrors(['hidden', 'verified']);
    $this->post(route('admin.dj.tracks.bulk.verify'), ['track_ids' => [$unmatched->id], 'verified' => true, 'hidden' => true])
        ->assertSessionHasErrors('hidden');
});

test('the library and canonical views can be filtered to what is hidden from requests', function () {
    $hiddenArtist = CanonicalArtist::factory()->create(['hidden_from_requests_at' => now()]);
    $hiddenSong = CanonicalTrack::factory()->create(['hidden_from_requests_at' => now()]);
    $songByHiddenArtist = CanonicalTrack::factory()->for($hiddenArtist, 'artist')->create();
    $shownSong = CanonicalTrack::factory()->create();
    $hiddenTrack = LibraryTrack::factory()->create(['canonical_track_id' => $hiddenSong->id]);
    LibraryTrack::factory()->create(['canonical_track_id' => $shownSong->id]);
    $hiddenLibraryArtist = LibraryArtist::factory()->create(['canonical_artist_id' => $hiddenArtist->id]);
    LibraryArtist::factory()->create();

    $this->get(route('admin.dj.tracks.index', ['filter' => ['hidden' => '1']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tracks.data', 1)
            ->where('tracks.data.0.id', $hiddenTrack->id)
            ->where('tracks.data.0.is_hidden_from_requests', true)
        );

    $this->get(route('admin.dj.artists.index', ['filter' => ['hidden' => '1']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('artists.data', 1)
            ->where('artists.data.0.id', $hiddenLibraryArtist->id)
            ->where('artists.data.0.is_hidden_from_requests', true)
        );

    $this->get(route('admin.dj.songs.index', ['filter' => ['hidden' => '1'], 'sort' => 'title']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('songs.data', 2)
            ->where('songs.data', fn ($songs) => collect($songs)->pluck('id')->sort()->values()->all() === collect([$hiddenSong->id, $songByHiddenArtist->id])->sort()->values()->all())
        );

    $this->get(route('admin.dj.canonical-artists.index', ['filter' => ['hidden' => '1']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('artists.data', 1)
            ->where('artists.data.0.is_hidden_from_requests', true)
        );
});

test('the song page says whether it can be requested', function () {
    $song = songInLibrary();
    $orphan = CanonicalTrack::factory()->create();

    $this->get(route('admin.dj.songs.show', $song))
        ->assertInertia(fn (Assert $page) => $page
            ->where('song.is_hidden_from_requests', false)
            ->where('song.is_requestable', true)
        );

    $this->get(route('admin.dj.songs.show', $orphan))
        ->assertInertia(fn (Assert $page) => $page->where('song.is_requestable', false));
});

test('hiding needs the manage library permission and the DJ module', function () {
    $song = CanonicalTrack::factory()->create();
    $library = Library::factory()->create();
    $track = LibraryTrack::factory()->create(['canonical_track_id' => $song->id]);

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));

    $this->patch(route('admin.dj.canonical-tracks.hide', $song), ['hidden' => true])->assertForbidden();
    $this->patch(route('admin.dj.libraries.hide', $library), ['hidden' => true])->assertForbidden();
    $this->post(route('admin.dj.tracks.bulk.hide'), ['track_ids' => [$track->id], 'hidden' => true])->assertForbidden();

    $this->actingAs($this->user);
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->patch(route('admin.dj.canonical-tracks.hide', $song), ['hidden' => true])->assertNotFound();

    expect($song->fresh()->isHiddenFromRequests())->toBeFalse();
});

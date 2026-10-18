<?php

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\MergeAction;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Dj\Matching\CanonicalRematcher;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([DjPermission::ViewLibrary, DjPermission::ManageLibrary]);
    $this->actingAs($this->user);
});

test('selected tracks can be assigned to an existing song, which is verified', function () {
    $song = CanonicalTrack::factory()->create(['title' => 'Glue']);
    $old = CanonicalTrack::factory()->create(['title' => 'Glue (Dirty)']);
    $keptOld = CanonicalTrack::factory()->create(['title' => 'Glu']);
    $artist = LibraryArtist::factory()->create();
    $first = LibraryTrack::factory()->create(['canonical_track_id' => $old->id, 'library_artist_id' => $artist->id]);
    $second = LibraryTrack::factory()->create(['canonical_track_id' => $keptOld->id]);
    // Still linked, so its old song stays
    LibraryTrack::factory()->create(['canonical_track_id' => $keptOld->id]);

    $this->post(route('admin.dj.tracks.bulk.assign'), [
        'track_ids' => [$first->id, $second->id],
        'song_id' => $song->id,
    ])->assertRedirect()->assertSessionHas('success', trans_choice('dj.bulk.flash.tracks_assigned', 2, ['count' => 2, 'song' => 'Glue']));

    expect($first->fresh()->canonical_track_id)->toBe($song->id)
        ->and($second->fresh()->canonical_track_id)->toBe($song->id)
        // Each track keeps its own artist
        ->and($first->fresh()->library_artist_id)->toBe($artist->id)
        ->and($song->fresh()->isVerified())->toBeTrue()
        ->and($old->fresh())->toBeNull()
        ->and($keptOld->fresh())->not->toBeNull();

    $logs = CanonicalMerge::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('action')->unique()->all())->toBe([MergeAction::Assign])
        ->and($logs->pluck('source_name')->all())->toBe(['Glue (Dirty)', 'Glu'])
        ->and($logs->pluck('user_id')->unique()->all())->toBe([$this->user->id]);
});

test('a re-match never moves assigned tracks back', function () {
    $artist = CanonicalArtist::factory()->create();
    $song = CanonicalTrack::factory()->create(['title' => 'Different Song', 'canonical_artist_id' => $artist->id]);
    $track = LibraryTrack::factory()->create(['title' => 'Glue', 'canonical_track_id' => CanonicalTrack::factory()->create(['title' => 'Glue', 'canonical_artist_id' => $artist->id])->id]);

    $this->post(route('admin.dj.tracks.bulk.assign'), ['track_ids' => [$track->id], 'song_id' => $song->id]);
    CanonicalTrack::factory()->create(['title' => 'Glue', 'canonical_artist_id' => $artist->id]);
    app(CanonicalRematcher::class)->run();

    expect($track->fresh()->canonical_track_id)->toBe($song->id);
});

test('selected tracks can be assigned to a new song, named after the first by default', function (?string $title, string $expected) {
    $canonicalArtist = CanonicalArtist::factory()->create();
    $artist = LibraryArtist::factory()->create(['canonical_artist_id' => $canonicalArtist->id]);
    $first = LibraryTrack::factory()->create(['title' => 'Glue (Dirty)', 'library_artist_id' => $artist->id]);
    $second = LibraryTrack::factory()->create(['title' => 'Glue (Clean)']);

    $response = $this->post(route('admin.dj.tracks.bulk.assign'), [
        'track_ids' => [$first->id, $second->id],
        'new_song_title' => $title,
    ]);

    if ($title === null) {
        $response->assertSessionHasErrors('new_song_title');

        return;
    }

    $response->assertSessionHasNoErrors();

    $song = $first->fresh()->canonicalTrack;

    expect($song->title)->toBe($expected)
        ->and($song->canonical_artist_id)->toBe($canonicalArtist->id)
        ->and($song->isVerified())->toBeTrue()
        ->and($second->fresh()->canonical_track_id)->toBe($song->id);
})->with([
    'a title' => ['Glue', 'Glue'],
    'no title or song' => [null, ''],
]);

test('selected library artists can be assigned to another artist, taking songs only they have', function () {
    $dave = CanonicalArtist::factory()->create(['name' => 'Dave']);
    $ukDave = LibraryArtist::factory()->create(['name' => 'Dave', 'canonical_artist_id' => $dave->id]);
    $otherDave = LibraryArtist::factory()->create(['name' => 'Dave', 'canonical_artist_id' => $dave->id]);
    $theirSong = CanonicalTrack::factory()->create(['canonical_artist_id' => $dave->id]);
    LibraryTrack::factory()->create(['library_artist_id' => $otherDave->id, 'canonical_track_id' => $theirSong->id]);
    $sharedSong = CanonicalTrack::factory()->create(['canonical_artist_id' => $dave->id]);
    LibraryTrack::factory()->create(['library_artist_id' => $otherDave->id, 'canonical_track_id' => $sharedSong->id]);
    LibraryTrack::factory()->create(['library_artist_id' => $ukDave->id, 'canonical_track_id' => $sharedSong->id]);

    // Splitting the other Dave off into a new artist
    $this->post(route('admin.dj.artists.bulk.assign'), [
        'artist_ids' => [$otherDave->id],
        'new_artist_name' => 'Dave (DJ)',
    ])->assertSessionHasNoErrors();

    $newArtist = CanonicalArtist::query()->where('name', 'Dave (DJ)')->sole();

    expect($otherDave->fresh()->canonical_artist_id)->toBe($newArtist->id)
        ->and($ukDave->fresh()->canonical_artist_id)->toBe($dave->id)
        ->and($theirSong->fresh()->canonical_artist_id)->toBe($newArtist->id)
        ->and($sharedSong->fresh()->canonical_artist_id)->toBe($dave->id)
        ->and($newArtist->isVerified())->toBeTrue()
        ->and(CanonicalMerge::sole())->record->toBe(CanonicalRecord::Artist)->action->toBe(MergeAction::Assign);
});

test('selected tracks and artists can have their canonical records verified in bulk', function () {
    $song = CanonicalTrack::factory()->create();
    $tracks = LibraryTrack::factory()->count(2)->create(['canonical_track_id' => $song->id]);
    $artist = CanonicalArtist::factory()->create();
    $libraryArtist = LibraryArtist::factory()->create(['canonical_artist_id' => $artist->id]);

    $this->post(route('admin.dj.tracks.bulk.verify'), ['track_ids' => $tracks->modelKeys(), 'verified' => true])
        ->assertSessionHas('success', trans_choice('dj.bulk.flash.songs_verified', 1, ['count' => 1]));
    $this->post(route('admin.dj.artists.bulk.verify'), ['artist_ids' => [$libraryArtist->id], 'verified' => true]);

    expect($song->fresh()->isVerified())->toBeTrue()->and($artist->fresh()->isVerified())->toBeTrue();

    $this->post(route('admin.dj.tracks.bulk.verify'), ['track_ids' => $tracks->modelKeys(), 'verified' => false]);
    expect($song->fresh()->isVerified())->toBeFalse();
});

test('bulk actions are validated', function (string $route, Closure $data, string $error) {
    $this->post(route($route), $data())->assertSessionHasErrors($error);
})->with([
    'no tracks' => ['admin.dj.tracks.bulk.assign', fn () => ['track_ids' => [], 'new_song_title' => 'Glue'], 'track_ids'],
    'more than a page' => ['admin.dj.tracks.bulk.assign', fn () => ['track_ids' => range(1, 101), 'new_song_title' => 'Glue'], 'track_ids'],
    'a missing track' => ['admin.dj.tracks.bulk.assign', fn () => ['track_ids' => [999], 'new_song_title' => 'Glue'], 'track_ids.0'],
    'a missing song' => ['admin.dj.tracks.bulk.assign', fn () => ['track_ids' => [LibraryTrack::factory()->create()->id], 'song_id' => 999], 'song_id'],
    'verifying without saying which way' => ['admin.dj.tracks.bulk.verify', fn () => ['track_ids' => [LibraryTrack::factory()->create()->id]], 'verified'],
    'no artist or name' => ['admin.dj.artists.bulk.assign', fn () => ['artist_ids' => [LibraryArtist::factory()->create()->id]], 'new_artist_name'],
]);

test('the library pages offer bulk actions only to library managers', function () {
    $this->get(route('admin.dj.tracks.index'))->assertInertia(fn (Assert $page) => $page->where('canManageMatching', true));
    $this->get(route('admin.dj.artists.index'))->assertInertia(fn (Assert $page) => $page->where('canManageMatching', true));

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));
    $track = LibraryTrack::factory()->create();
    $artist = LibraryArtist::factory()->create();

    $this->get(route('admin.dj.tracks.index'))->assertInertia(fn (Assert $page) => $page->where('canManageMatching', false));
    $this->post(route('admin.dj.tracks.bulk.assign'), ['track_ids' => [$track->id], 'new_song_title' => 'X'])->assertForbidden();
    $this->post(route('admin.dj.tracks.bulk.verify'), ['track_ids' => [$track->id], 'verified' => true])->assertForbidden();
    $this->post(route('admin.dj.artists.bulk.assign'), ['artist_ids' => [$artist->id], 'new_artist_name' => 'X'])->assertForbidden();
    $this->post(route('admin.dj.artists.bulk.verify'), ['artist_ids' => [$artist->id], 'verified' => true])->assertForbidden();
});

test('bulk actions are not found when the tenant does not have the DJ module', function () {
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->post(route('admin.dj.tracks.bulk.assign'), [])->assertNotFound();
});

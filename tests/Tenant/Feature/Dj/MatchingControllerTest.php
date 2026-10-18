<?php

use App\Enums\Dj\CanonicalRecord;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\MatchDismissal;
use App\Services\Authorisation\Enums\DjPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([DjPermission::ViewLibrary, DjPermission::ManageLibrary]);
    $this->actingAs($this->user);
});

test('the matching page lists suggestions, unmatched tracks and recent changes', function () {
    $artist = CanonicalArtist::factory()->create(['name' => 'Justin Timberlake', 'match_key' => 'justin timberlake']);
    CanonicalArtist::factory()->create(['name' => 'Juston Timberlake', 'match_key' => 'juston timberlake']);
    CanonicalTrack::factory()->create(['title' => 'Mirrors', 'match_key' => 'mirrors', 'canonical_artist_id' => $artist->id]);
    CanonicalTrack::factory()->create(['title' => 'Mirors', 'match_key' => 'mirors', 'canonical_artist_id' => $artist->id]);
    LibraryTrack::factory()->create(['canonical_track_id' => null]);
    CanonicalMerge::query()->create([
        'record' => CanonicalRecord::Track, 'action' => 'merge', 'target_name' => 'Glue', 'source_name' => 'Glu', 'moved_count' => 2,
    ]);

    $this->get(route('admin.dj.matching.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/matching/Index')
            // Loaded once the page has shown
            ->missing('trackSuggestions')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('trackSuggestions', 1)
                ->where('trackSuggestions.0.first.name', 'Mirrors')
                ->where('trackSuggestions.0.first.detail', 'Justin Timberlake')
                ->has('artistSuggestions', 1)
            )
            ->where('needsRematch', false)
            ->where('unmatchedCount', 1)
            ->where('merges.data.0.source_name', 'Glu')
            ->where('merges.data.0.user', null)
            ->where('canManage', true)
        );
});

test('a suggested pair can be merged, keeping the chosen record', function (CanonicalRecord $record) {
    [$kept, $merged] = $record === CanonicalRecord::Artist
        ? CanonicalArtist::factory()->count(2)->create()
        : CanonicalTrack::factory()->count(2)->create();

    $this->post(route('admin.dj.matching.merges.store'), [
        'record' => $record->value,
        'target_id' => $kept->id,
        'source_id' => $merged->id,
    ])->assertRedirect()->assertSessionHas('success');

    expect($merged->fresh())->toBeNull()
        ->and($kept->fresh())->not->toBeNull()
        ->and(CanonicalMerge::sole()->user_id)->toBe($this->user->id);
})->with([CanonicalRecord::Artist, CanonicalRecord::Track]);

test('a record cannot be merged into itself, or into one that does not exist', function (Closure $data, string $error) {
    $track = CanonicalTrack::factory()->create();

    $this->post(route('admin.dj.matching.merges.store'), $data($track))->assertSessionHasErrors($error);
})->with([
    'itself' => [fn ($track) => ['record' => 'track', 'target_id' => $track->id, 'source_id' => $track->id], 'source_id'],
    'a missing record' => [fn ($track) => ['record' => 'track', 'target_id' => $track->id, 'source_id' => 999], 'source_id'],
    'an artist ID for a track' => [fn ($track) => ['record' => 'artist', 'target_id' => CanonicalArtist::factory()->create()->id, 'source_id' => $track->id + 999], 'source_id'],
    'an unknown record type' => [fn ($track) => ['record' => 'album', 'target_id' => $track->id, 'source_id' => $track->id], 'record'],
]);

test('a suggested pair can be dismissed', function () {
    [$first, $second] = CanonicalArtist::factory()->count(2)->create();

    $this->post(route('admin.dj.matching.dismissals.store'), [
        'record' => 'artist',
        'first_id' => $second->id,
        'second_id' => $first->id,
    ])->assertRedirect();

    expect(MatchDismissal::sole())
        ->first_id->toBe(min($first->id, $second->id))
        ->second_id->toBe(max($first->id, $second->id))
        ->user_id->toBe($this->user->id);
});

test('the library can be re-matched', function () {
    $artist = CanonicalArtist::factory()->create();
    CanonicalTrack::factory()->create(['title' => 'Glue', 'canonical_artist_id' => $artist->id]);
    CanonicalTrack::factory()->create(['title' => 'Glue (Dirty)', 'canonical_artist_id' => $artist->id]);

    $this->post(route('admin.dj.matching.rematch'))
        ->assertRedirect(route('admin.dj.matching.index'))
        ->assertSessionHas('success', __('dj.matching.flash.rematched', ['artists' => 0, 'tracks' => 1, 'linked' => 0]));

    expect(CanonicalTrack::count())->toBe(1);
});

test('a library track can be split off, and a canonical track verified', function () {
    $canonical = CanonicalTrack::factory()->create();
    $track = LibraryTrack::factory()->create(['canonical_track_id' => $canonical->id]);
    // Another track keeps the canonical track in use after the split
    LibraryTrack::factory()->create(['canonical_track_id' => $canonical->id]);

    $this->post(route('admin.dj.tracks.split', $track))->assertRedirect()->assertSessionHas('success', __('dj.matching.flash.split'));

    expect($track->fresh()->canonical_track_id)->not->toBe($canonical->id);

    $this->patch(route('admin.dj.canonical-tracks.verify', $canonical), ['verified' => true])->assertRedirect();
    expect($canonical->fresh()->isVerified())->toBeTrue();

    $this->patch(route('admin.dj.canonical-tracks.verify', $canonical), ['verified' => false])->assertRedirect();
    expect($canonical->fresh()->isVerified())->toBeFalse();
});

test('canonical tracks can be searched for a merge, leaving out the one being merged', function () {
    $self = CanonicalTrack::factory()->create(['title' => 'Glue']);
    CanonicalTrack::factory()->create(['title' => 'Glue (Bicep Edit)']);
    CanonicalTrack::factory()->create(['title' => 'Atlas']);

    $this->getJson(route('admin.dj.canonical-tracks.search', ['search' => 'glue', 'except' => $self->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Glue (Bicep Edit)');
});

test('the track page lets library managers change its matching', function () {
    $track = LibraryTrack::factory()->create(['canonical_track_id' => CanonicalTrack::factory()->create()->id]);

    $this->get(route('admin.dj.tracks.show', $track))
        ->assertInertia(fn (Assert $page) => $page->where('canManageMatching', true)->has('track.data.canonical.id'));
});

test('users who can only view the library can review but not change matching', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));
    [$first, $second] = CanonicalTrack::factory()->count(2)->create();
    $track = LibraryTrack::factory()->create(['canonical_track_id' => $first->id]);

    $this->get(route('admin.dj.matching.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->get(route('admin.dj.tracks.show', $track))->assertInertia(fn (Assert $page) => $page->where('canManageMatching', false));

    $this->post(route('admin.dj.matching.rematch'))->assertForbidden();
    $this->post(route('admin.dj.matching.merges.store'), ['record' => 'track', 'target_id' => $first->id, 'source_id' => $second->id])->assertForbidden();
    $this->post(route('admin.dj.matching.dismissals.store'), ['record' => 'track', 'first_id' => $first->id, 'second_id' => $second->id])->assertForbidden();
    $this->post(route('admin.dj.tracks.split', $track))->assertForbidden();
    $this->patch(route('admin.dj.canonical-tracks.verify', $first), ['verified' => true])->assertForbidden();

    expect(CanonicalTrack::count())->toBe(2)->and(CanonicalMerge::count())->toBe(0);
});

test('users without library permissions cannot see matching', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->get(route('admin.dj.matching.index'))->assertForbidden();
    $this->getJson(route('admin.dj.canonical-tracks.search'))->assertForbidden();
});

test('matching is not found when the tenant does not have the DJ module', function () {
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.dj.matching.index'))->assertNotFound();
    $this->post(route('admin.dj.matching.rematch'))->assertNotFound();
});

test('the matching page asks for a re-match when records have no match keys yet', function () {
    CanonicalTrack::factory()->create(['match_key' => null]);

    $this->get(route('admin.dj.matching.index'))
        ->assertInertia(fn (Assert $page) => $page->where('needsRematch', true));
});

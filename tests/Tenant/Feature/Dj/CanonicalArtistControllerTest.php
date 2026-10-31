<?php

use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryArtist;
use App\Services\Authorisation\Enums\DjPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary, DjPermission::ManageLibrary]));
});

test('artists are listed with their song and library artist counts', function () {
    $bicep = CanonicalArtist::factory()->create(['name' => 'Bicep']);
    CanonicalTrack::factory()->count(2)->create(['canonical_artist_id' => $bicep->id]);
    LibraryArtist::factory()->create(['canonical_artist_id' => $bicep->id]);
    CanonicalArtist::factory()->create(['name' => 'Avalon Emerson']);

    $this->get(route('admin.dj.canonical-artists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/canonical-artist/Index')
            ->where('artists.data.0.name', 'Avalon Emerson')
            ->where('artists.data.1.name', 'Bicep')
            ->where('artists.data.1.tracks_count', 2)
            ->where('artists.data.1.library_artists_count', 1)
        );

    $this->get(route('admin.dj.canonical-artists.index', ['filter' => ['orphaned' => '1']]))
        ->assertInertia(fn (Assert $page) => $page->has('artists.data', 1)->where('artists.data.0.name', 'Avalon Emerson'));

    $this->get(route('admin.dj.canonical-artists.index', ['sort' => '-tracks_count']))
        ->assertInertia(fn (Assert $page) => $page->where('artists.data.0.name', 'Bicep'));
});

test('an artist shows their songs and library artists', function () {
    $artist = CanonicalArtist::factory()->create(['name' => 'Bicep']);
    CanonicalTrack::factory()->create(['title' => 'Glue', 'canonical_artist_id' => $artist->id]);
    LibraryArtist::factory()->create(['name' => 'BICEP', 'canonical_artist_id' => $artist->id]);

    $this->get(route('admin.dj.canonical-artists.show', $artist))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/canonical-artist/Show')
            ->where('artist.name', 'Bicep')
            ->where('songs.0.title', 'Glue')
            ->where('songsCount', 1)
            ->where('libraryArtists.0.name', 'BICEP')
            ->where('canManage', true)
        );
});

test('an artist has an edit page with their name', function () {
    $artist = CanonicalArtist::factory()->create(['name' => 'Bicep']);

    $this->get(route('admin.dj.canonical-artists.edit', $artist))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/canonical-artist/Edit')
            ->where('artist.name', 'Bicep')
            ->where('artist.is_verified', false)
        );
});

test('renaming an artist verifies it', function () {
    $artist = CanonicalArtist::factory()->create(['name' => 'Bicep ft. Clara La San']);

    $this->patch(route('admin.dj.canonical-artists.update', $artist), ['name' => 'Bicep'])
        ->assertRedirect(route('admin.dj.canonical-artists.show', $artist));

    expect($artist->fresh())->name->toBe('Bicep')->match_key->toBe('bicep')
        ->and($artist->fresh()->isVerified())->toBeTrue();

    $this->patch(route('admin.dj.canonical-artists.update', $artist), ['name' => ''])->assertSessionHasErrors('name');
});

test('an artist can be verified and unverified', function () {
    $artist = CanonicalArtist::factory()->create();

    $this->patch(route('admin.dj.canonical-artists.verify', $artist), ['verified' => true]);
    expect($artist->fresh()->isVerified())->toBeTrue();

    $this->patch(route('admin.dj.canonical-artists.verify', $artist), ['verified' => false]);
    expect($artist->fresh()->isVerified())->toBeFalse();
});

test('artists can be searched, leaving one out', function () {
    $self = CanonicalArtist::factory()->create(['name' => 'Bicep']);
    CanonicalArtist::factory()->create(['name' => 'Bicep & Clara']);
    CanonicalArtist::factory()->create(['name' => 'Drake']);

    $this->getJson(route('admin.dj.canonical-artists.search', ['search' => 'bicep', 'except' => $self->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bicep & Clara');
});

test('users who can only view the library cannot change artists', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));
    $artist = CanonicalArtist::factory()->create(['name' => 'Bicep']);

    $this->get(route('admin.dj.canonical-artists.show', $artist))->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->get(route('admin.dj.canonical-artists.edit', $artist))->assertForbidden();
    $this->patch(route('admin.dj.canonical-artists.update', $artist), ['name' => 'Changed'])->assertForbidden();
    $this->patch(route('admin.dj.canonical-artists.verify', $artist), ['verified' => true])->assertForbidden();

    expect($artist->fresh()->name)->toBe('Bicep');
});

test('artists are not found without library permissions or the DJ module', function () {
    $this->actingAs($this->createUserWithPermissions([]));
    $this->get(route('admin.dj.canonical-artists.index'))->assertForbidden();
    $this->getJson(route('admin.dj.canonical-artists.search'))->assertForbidden();

    tenant()->update(['modules' => [TenantModule::Cms]]);
    $this->get(route('admin.dj.canonical-artists.index'))->assertNotFound();
});

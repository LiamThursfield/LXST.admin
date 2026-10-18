<?php

use App\Enums\Dj\SetlistSongType;
use App\Enums\Dj\SongListKind;
use App\Enums\Dj\SongListVisibility;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageSetlists]);
    $this->actingAs($this->user);
});

test('admins and editors can manage setlists, and viewers only view them', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::findByName(RoleEnum::Admin->value)->hasPermissionTo(DjPermission::ManageSetlists->value))->toBeTrue()
        ->and(Role::findByName(RoleEnum::Editor->value)->hasPermissionTo(DjPermission::ManageSetlists->value))->toBeTrue()
        ->and(Role::findByName(RoleEnum::Viewer->value)->hasPermissionTo(DjPermission::ManageSetlists->value))->toBeFalse();
});

test('setlists are listed with their gig and song counts, but a gig\'s requests list is not', function () {
    $gig = Gig::factory()->create(['name' => 'Wedding']);
    $setlist = SongList::factory()->forGig($gig)->create(['name' => 'Wedding plan']);
    SongListItem::factory()->for($setlist)->count(2)->create(['type' => SetlistSongType::MustPlay]);
    SongListItem::factory()->for($setlist)->create(['type' => SetlistSongType::DoNotPlay]);
    SongList::factory()->forGig($gig)->requests()->create();
    SongList::factory()->create(['name' => 'Ideas']);

    $this->get(route('admin.dj.setlists.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/setlist/Index')
            ->has('setlists.data', 2)
            ->where('canCreate', true)
        );

    $this->get(route('admin.dj.setlists.index', ['filter' => ['search' => 'wedd']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('setlists.data', 1)
            ->where('setlists.data.0.name', 'Wedding plan')
            ->where('setlists.data.0.gig.name', 'Wedding')
            ->where('setlists.data.0.counts.must_play', 2)
            ->where('setlists.data.0.counts.do_not_play', 1)
        );
});

test('a setlist can be created for a gig, or none, and edited', function () {
    $gig = Gig::factory()->create();

    $this->post(route('admin.dj.setlists.store'), ['name' => 'First dance', 'gig_id' => $gig->id, 'visibility' => 'public'])
        ->assertRedirect();

    $setlist = SongList::sole();

    expect($setlist->kind)->toBe(SongListKind::Setlist)
        ->and($setlist->gig_id)->toBe($gig->id)
        ->and($setlist->visibility)->toBe(SongListVisibility::Public);

    $this->put(route('admin.dj.setlists.update', $setlist), ['name' => 'Evening', 'gig_id' => null, 'visibility' => 'private'])
        ->assertRedirect();

    expect($setlist->fresh())->name->toBe('Evening')->gig_id->toBeNull();

    $this->post(route('admin.dj.setlists.store'), ['name' => '', 'visibility' => 'secret', 'gig_id' => 999])
        ->assertSessionHasErrors(['name', 'visibility', 'gig_id']);
});

test('songs can be added from the library or as custom songs, by whoever is logged in', function () {
    $setlist = SongList::factory()->create();
    $song = CanonicalTrack::factory()->for(CanonicalArtist::factory()->create(['name' => 'Bicep']), 'artist')->create(['title' => 'Glue']);

    $this->post(route('admin.dj.setlists.items.store', $setlist), ['canonical_track_id' => $song->id, 'type' => 'must_play', 'note' => 'First dance'])
        ->assertRedirect()->assertSessionHas('success', __('dj.setlists.flash.song_added'));
    $this->post(route('admin.dj.setlists.items.store', $setlist), ['custom_artist' => 'Cousin Dave', 'custom_title' => 'Our Song', 'type' => 'maybe'])
        ->assertRedirect();

    $this->get(route('admin.dj.setlists.show', $setlist))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/setlist/Show')
            ->has('items.data', 2)
            ->where('items.data.0.title', 'Glue')
            ->where('items.data.0.artist', 'Bicep')
            ->where('items.data.0.note', 'First dance')
            ->where('items.data.0.added_by', $this->user->name)
            ->where('items.data.1.title', 'Our Song')
            ->where('items.data.1.artist', 'Cousin Dave')
            ->where('items.data.1.is_custom', true)
            ->where('items.data.1.type', 'maybe')
            ->where('canManage', true)
        );
});

test('a song needs a library song or a custom artist, and a type', function (array $data, string $error) {
    $setlist = SongList::factory()->create();

    $this->post(route('admin.dj.setlists.items.store', $setlist), $data)->assertSessionHasErrors($error);
})->with([
    'neither' => [['type' => 'must_play'], 'custom_artist'],
    'both' => [fn () => ['canonical_track_id' => CanonicalTrack::factory()->create()->id, 'custom_artist' => 'X', 'type' => 'must_play'], 'custom_artist'],
    'no type' => [['custom_artist' => 'X'], 'type'],
    'an unknown type' => [['custom_artist' => 'X', 'type' => 'never'], 'type'],
]);

test('songs can be retyped, noted, reordered and removed', function () {
    $setlist = SongList::factory()->create();
    [$first, $second, $third] = SongListItem::factory()->for($setlist)->count(3)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();

    $this->patch(route('admin.dj.setlists.items.update', [$setlist, $first]), ['type' => 'do_not_play', 'note' => 'Not this one'])->assertRedirect();
    expect($first->fresh())->type->toBe(SetlistSongType::DoNotPlay)->note->toBe('Not this one');

    $this->put(route('admin.dj.setlists.items.reorder', $setlist), ['item_ids' => [$third->id, $first->id]])->assertRedirect();
    expect($setlist->items()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id]);

    $this->delete(route('admin.dj.setlists.items.destroy', [$setlist, $second]))->assertRedirect();
    expect($second->fresh())->toBeNull();
});

test('songs on another list cannot be changed through this one', function () {
    $setlist = SongList::factory()->create();
    $other = SongListItem::factory()->create();

    $this->patch(route('admin.dj.setlists.items.update', [$setlist, $other]), ['type' => 'maybe'])->assertNotFound();
    $this->delete(route('admin.dj.setlists.items.destroy', [$setlist, $other]))->assertNotFound();
});

test('a setlist can be duplicated, songs and all, for another gig', function () {
    $setlist = SongList::factory()->forGig()->create(['name' => 'Smith wedding']);
    SongListItem::factory()->for($setlist)->create(['type' => SetlistSongType::MustPlay, 'note' => 'First dance', 'position' => 1]);
    SongListItem::factory()->for($setlist)->custom('Cousin Dave')->create(['type' => SetlistSongType::DoNotPlay, 'position' => 2]);
    $gig = Gig::factory()->create();

    $this->post(route('admin.dj.setlists.duplicate', $setlist), ['name' => 'Smith anniversary', 'gig_id' => $gig->id])->assertRedirect();

    $copy = SongList::query()->where('name', 'Smith anniversary')->sole();

    expect($copy->gig_id)->toBe($gig->id)
        ->and($copy->items()->count())->toBe(2)
        ->and($copy->items->first()->note)->toBe('First dance')
        ->and($copy->items->last()->custom_artist)->toBe('Cousin Dave')
        ->and($copy->items->last()->type)->toBe(SetlistSongType::DoNotPlay)
        ->and($setlist->items()->count())->toBe(2);
});

test('a setlist can be deleted with its songs', function () {
    $setlist = SongList::factory()->create();
    SongListItem::factory()->for($setlist)->create();

    $this->delete(route('admin.dj.setlists.destroy', $setlist))->assertRedirect(route('admin.dj.setlists.index'));

    expect($setlist->fresh())->toBeNull()->and(SongListItem::count())->toBe(0);
});

test('a gig\'s requests list is not handled by the setlist pages', function () {
    $requests = SongList::factory()->forGig()->requests()->create();

    $this->get(route('admin.dj.setlists.show', $requests))->assertNotFound();
    $this->post(route('admin.dj.setlists.items.store', $requests), ['custom_artist' => 'X', 'type' => 'maybe'])->assertNotFound();
    $this->delete(route('admin.dj.setlists.destroy', $requests))->assertNotFound();
});

test('gig and song pages show their setlists', function () {
    $gig = Gig::factory()->create();
    $setlist = SongList::factory()->forGig($gig)->create(['name' => 'Wedding plan']);
    $song = CanonicalTrack::factory()->create();
    SongListItem::factory()->for($setlist)->create(['canonical_track_id' => $song->id, 'type' => SetlistSongType::DoNotPlay]);

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs, DjPermission::ManageSetlists, DjPermission::ViewLibrary]));

    $this->get(route('admin.dj.gigs.edit', $gig))
        ->assertInertia(fn (Assert $page) => $page->where('setlists.0.name', 'Wedding plan')->where('setlists.0.items_count', 1)->where('canCreateSetlist', true));

    $this->get(route('admin.dj.songs.show', $song))
        ->assertInertia(fn (Assert $page) => $page->where('setlists.0.name', 'Wedding plan')->where('setlists.0.type', 'Do not play'));
});

test('users who can only view gigs can see setlists but not change them', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs]));
    $setlist = SongList::factory()->create();
    $item = SongListItem::factory()->for($setlist)->create();

    $this->get(route('admin.dj.setlists.show', $setlist))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->post(route('admin.dj.setlists.store'), ['name' => 'X', 'visibility' => 'private'])->assertForbidden();
    $this->put(route('admin.dj.setlists.update', $setlist), ['name' => 'X', 'visibility' => 'private'])->assertForbidden();
    $this->post(route('admin.dj.setlists.items.store', $setlist), ['custom_artist' => 'X', 'type' => 'maybe'])->assertForbidden();
    $this->patch(route('admin.dj.setlists.items.update', [$setlist, $item]), ['type' => 'maybe'])->assertForbidden();
    $this->put(route('admin.dj.setlists.items.reorder', $setlist), ['item_ids' => [$item->id]])->assertForbidden();
    $this->post(route('admin.dj.setlists.duplicate', $setlist), ['name' => 'X'])->assertForbidden();
    $this->delete(route('admin.dj.setlists.destroy', $setlist))->assertForbidden();
});

test('setlists are not found without gig permissions or the DJ module', function () {
    $this->actingAs($this->createUserWithPermissions([]));
    $this->get(route('admin.dj.setlists.index'))->assertForbidden();

    tenant()->update(['modules' => [TenantModule::Cms]]);
    $this->get(route('admin.dj.setlists.index'))->assertNotFound();
});

<?php

use App\Enums\Dj\SongListKind;
use App\Enums\TenantModule;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Models\Settings\DjSettings;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Dj\GuestPasses\GuestPasswordGenerator;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs, DjPermission::ManageSetlists, DjPermission::ManageRequests]);
    $this->actingAs($this->user);
});

test('a setlist gets a pass with a generated password, stored encrypted', function () {
    $setlist = SongList::factory()->create();

    $this->post(route('admin.dj.song-lists.guest-passes.store', $setlist), ['label' => 'Bride', 'can_add' => true])
        ->assertRedirect()->assertSessionHas('success', __('dj.guest_passes.flash.created'));

    $pass = GuestPass::sole();

    expect($pass->song_list_id)->toBe($setlist->id)
        ->and($pass->label)->toBe('Bride')
        ->and($pass->password)->toMatch('/^[a-z]+-[a-z]+-\d{2}$/')
        ->and(DB::table('dj_guest_passes')->value('password'))->not->toContain($pass->password);
});

test('the DJ can choose the password, an expiry day, and whether it can add songs', function () {
    app(DjSettings::class)->fill(['default_timezone' => 'Europe/London'])->save();
    $setlist = SongList::factory()->create();

    $this->post(route('admin.dj.song-lists.guest-passes.store', $setlist), [
        'label' => 'Planner',
        'password' => 'first-dance',
        'can_add' => false,
        'expires_on' => now()->addMonth()->format('Y-m-d'),
    ])->assertRedirect();

    $pass = GuestPass::sole();

    expect($pass->password)->toBe('first-dance')
        ->and($pass->can_add)->toBeFalse()
        // The end of that day in London
        ->and($pass->expires_at->setTimezone('Europe/London')->format('Y-m-d H:i'))->toBe(now()->addMonth()->format('Y-m-d').' 23:59');

    $this->post(route('admin.dj.song-lists.guest-passes.store', $setlist), [
        'label' => '',
        'password' => 'abc',
        'can_add' => 'sometimes',
        'expires_on' => now()->subDay()->format('Y-m-d'),
        'revoked' => true,
    ])->assertSessionHasErrors(['label', 'password', 'can_add', 'expires_on', 'revoked']);
});

test('a pass can be renamed, given a new password, revoked, restored and deleted, keeping its songs', function () {
    $pass = GuestPass::factory()->create(['password' => 'old-pass-11']);
    $item = SongListItem::factory()->for($pass->songList)->create(['guest_pass_id' => $pass->id]);

    // A blank password keeps the current one
    $this->put(route('admin.dj.guest-passes.update', $pass), ['label' => 'Family', 'can_add' => true])->assertRedirect();
    expect($pass->fresh())->label->toBe('Family')->password->toBe('old-pass-11');

    $this->put(route('admin.dj.guest-passes.update', $pass), ['label' => 'Family', 'can_add' => true, 'regenerate_password' => true]);
    expect($pass->fresh()->password)->not->toBe('old-pass-11')
        ->and(collect(GuestPasswordGenerator::WORDS))->toContain(explode('-', $pass->fresh()->password)[0]);

    $this->put(route('admin.dj.guest-passes.update', $pass), ['label' => 'Family', 'can_add' => true, 'revoked' => true]);
    expect($pass->fresh()->isRevoked())->toBeTrue();

    $this->put(route('admin.dj.guest-passes.update', $pass), ['label' => 'Family', 'can_add' => true, 'revoked' => false]);
    expect($pass->fresh()->isActive())->toBeTrue();

    $this->delete(route('admin.dj.guest-passes.destroy', $pass))->assertRedirect();

    expect(GuestPass::count())->toBe(0)
        ->and($item->fresh())->not->toBeNull()
        ->and($item->fresh()->guest_pass_id)->toBeNull();
});

test('the setlist page lists its passes, and songs show the pass and guest name that added them', function () {
    $setlist = SongList::factory()->create();
    $pass = GuestPass::factory()->for($setlist)->create(['label' => 'Family']);
    SongListItem::factory()->for($setlist)->create(['guest_pass_id' => $pass->id, 'guest_name' => 'Aunt Sue']);

    $this->get(route('admin.dj.setlists.show', $setlist))
        ->assertInertia(fn (Assert $page) => $page
            ->has('guestPasses.data', 1)
            ->where('guestPasses.data.0.label', 'Family')
            ->where('guestPasses.data.0.password', $pass->password)
            ->where('guestPasses.data.0.status', 'active')
            ->where('guestPasses.data.0.items_count', 1)
            ->where('items.data.0.added_by', 'Family · Aunt Sue')
        );
});

test('a gig gets request passes, making its requests list with the first one', function () {
    $gig = Gig::factory()->create(['name' => 'Wedding']);

    $this->get(route('admin.dj.gigs.edit', $gig))
        ->assertInertia(fn (Assert $page) => $page->has('requestPasses.data', 0));

    $this->post(route('admin.dj.gigs.guest-passes.store', $gig), ['label' => 'Guests', 'can_add' => true])->assertRedirect();
    $this->post(route('admin.dj.gigs.guest-passes.store', $gig), ['label' => 'Family', 'can_add' => true])->assertRedirect();

    $list = SongList::sole();

    expect($list->kind)->toBe(SongListKind::Requests)
        ->and($list->gig_id)->toBe($gig->id)
        ->and($list->guestPasses()->count())->toBe(2);

    $this->get(route('admin.dj.gigs.edit', $gig))
        ->assertInertia(fn (Assert $page) => $page->has('requestPasses.data', 2));
});

test('setlist passes need the manage setlists permission, and request passes manage requests', function () {
    $setlist = SongList::factory()->create();
    $gig = Gig::factory()->create();
    $pass = GuestPass::factory()->for($setlist)->create();

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs, DjPermission::ManageRequests]));

    $this->post(route('admin.dj.song-lists.guest-passes.store', $setlist), ['label' => 'Bride', 'can_add' => true])->assertForbidden();
    $this->put(route('admin.dj.guest-passes.update', $pass), ['label' => 'Bride', 'can_add' => true])->assertForbidden();
    $this->delete(route('admin.dj.guest-passes.destroy', $pass))->assertForbidden();
    $this->get(route('admin.dj.setlists.show', $setlist))->assertInertia(fn (Assert $page) => $page->where('guestPasses', null));
    $this->post(route('admin.dj.gigs.guest-passes.store', $gig), ['label' => 'Guests', 'can_add' => true])->assertRedirect();

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs, DjPermission::ManageSetlists]));

    $this->post(route('admin.dj.gigs.guest-passes.store', $gig), ['label' => 'Guests', 'can_add' => true])->assertForbidden();
    $this->get(route('admin.dj.gigs.edit', $gig))->assertInertia(fn (Assert $page) => $page->where('requestPasses', null));

    $this->actingAs($this->user);
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->post(route('admin.dj.song-lists.guest-passes.store', $setlist), ['label' => 'Bride', 'can_add' => true])->assertNotFound();
});

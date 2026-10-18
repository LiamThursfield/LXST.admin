<?php

use App\Data\Dj\GigRequestSettings;
use App\Enums\Dj\RequestsMode;
use App\Enums\Dj\RequestStatus;
use App\Enums\Dj\SetlistSongType;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\NavigationService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([DjPermission::ManageRequests]);
    $this->actingAs($this->user);

    $this->gig = Gig::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
        'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open),
    ]);
    $this->list = SongList::factory()->requests()->forGig($this->gig)->create();
});

/**
 * A request on the gig's list, with the given number of votes
 *
 * @param  array<string, mixed>  $attributes
 */
function queuedRequest(mixed $test, int $votes = 1, array $attributes = []): SongListItem
{
    $item = SongListItem::factory()->for($test->list)->create(['type' => null, 'status' => RequestStatus::Pending, ...$attributes]);

    foreach (range(1, $votes) as $vote) {
        $item->votes()->create(['visitor_hash' => fake()->sha256()]);
    }

    return $item;
}

/**
 * Whether the user's main menu has DJ › Requests
 */
function djMenuHasRequests(mixed $user): bool
{
    return app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)
        ->flatten()
        ->contains(fn (mixed $item) => $item instanceof MenuItem && $item->key === TenantMenu::MENU_MAIN_SECTION_DJ_ITEM_REQUESTS);
}

test('the queue shows the current gig\'s requests by status, pending by votes then age', function () {
    $older = queuedRequest($this, 2, ['created_at' => now()->subMinutes(10)]);
    $newer = queuedRequest($this, 2, ['created_at' => now()->subMinute()]);
    $popular = queuedRequest($this, 5);
    $accepted = queuedRequest($this, 1, ['status' => RequestStatus::Accepted]);
    queuedRequest($this, 1, ['status' => RequestStatus::Played, 'played_at' => now()]);
    queuedRequest($this, 1, ['status' => RequestStatus::Declined]);

    $this->get(route('admin.dj.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/requests/Index')
            ->where('gig.id', $this->gig->id)
            ->where('gig.requests_open', true)
            ->where('requests.pending', fn ($items) => collect($items)->pluck('id')->all() === [$popular->id, $older->id, $newer->id])
            ->has('requests.accepted', 1)
            ->where('requests.accepted.0.id', $accepted->id)
            ->has('requests.played', 1)
            ->has('requests.declined', 1)
        );
});

test('requests show their details, the pass that asked, and the gig\'s setlist flags', function () {
    $song = CanonicalTrack::factory()->create(['title' => 'Glue', 'bpm' => 130]);
    $pass = GuestPass::factory()->for($this->list)->create(['label' => 'Family']);
    queuedRequest($this, 1, [
        'canonical_track_id' => $song->id,
        'requester_name' => 'Sam',
        'message' => 'For my mum',
        'guest_pass_id' => $pass->id,
        'guest_name' => 'Aunt Sue',
    ]);
    queuedRequest($this, 1, ['canonical_track_id' => null, 'custom_artist' => 'Daft Punk', 'custom_title' => 'One More Time']);

    $setlist = SongList::factory()->forGig($this->gig)->create();
    SongListItem::factory()->for($setlist)->create(['canonical_track_id' => $song->id, 'type' => SetlistSongType::MustPlay]);
    SongListItem::factory()->for($setlist)->custom('daft punk', 'one more time.')->create(['type' => SetlistSongType::DoNotPlay]);
    // Another gig's setlist doesn't count
    SongListItem::factory()->for(SongList::factory()->forGig()->create())->create(['canonical_track_id' => $song->id, 'type' => SetlistSongType::DoNotPlay]);

    $this->get(route('admin.dj.requests.index', ['gig' => $this->gig->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.pending.0.title', 'Glue')
            ->where('requests.pending.0.in_library', true)
            ->where('requests.pending.0.bpm', 130)
            ->where('requests.pending.0.requester_name', 'Sam')
            ->where('requests.pending.0.message', 'For my mum')
            ->where('requests.pending.0.pass', 'Family · Aunt Sue')
            ->where('requests.pending.0.setlist_types', ['must_play'])
            ->where('requests.pending.1.in_library', false)
            ->where('requests.pending.1.setlist_types', ['do_not_play'])
        );
});

test('requests are accepted, declined, marked played, and undone back to pending', function () {
    $item = queuedRequest($this);

    $this->patch(route('admin.dj.requests.status', $item), ['status' => 'accepted'])->assertRedirect();
    expect($item->fresh()->status)->toBe(RequestStatus::Accepted);

    $this->patch(route('admin.dj.requests.status', $item), ['status' => 'played']);
    expect($item->fresh())->status->toBe(RequestStatus::Played)->played_at->not->toBeNull();

    $this->patch(route('admin.dj.requests.status', $item), ['status' => 'pending']);
    expect($item->fresh())->status->toBe(RequestStatus::Pending)->played_at->toBeNull();

    $this->patch(route('admin.dj.requests.status', $item), ['status' => 'declined']);
    expect($item->fresh()->status)->toBe(RequestStatus::Declined);

    $this->patch(route('admin.dj.requests.status', $item), ['status' => 'lost'])->assertSessionHasErrors('status');
});

test('setlist songs can\'t be given a request status', function () {
    $setlistSong = SongListItem::factory()->for(SongList::factory()->create())->create();

    $this->patch(route('admin.dj.requests.status', $setlistSong), ['status' => 'accepted'])->assertForbidden();
});

test('requests are opened, closed, and set back to following DJ settings, keeping other overrides', function () {
    $this->gig->update(['request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open, allow_upvotes: false)]);

    $this->patch(route('admin.dj.gigs.requests-mode', $this->gig), ['requests_mode' => 'closed'])
        ->assertRedirect()
        ->assertSessionHas('success', __('dj.queue.flash.closed'));

    expect($this->gig->fresh()->request_settings)
        ->requests_mode->toBe(RequestsMode::Closed)
        ->allow_upvotes->toBeFalse();

    $this->patch(route('admin.dj.gigs.requests-mode', $this->gig), ['requests_mode' => null]);

    expect($this->gig->fresh()->request_settings->requests_mode)->toBeNull()
        ->and($this->gig->fresh()->request_settings->allow_upvotes)->toBeFalse();

    $this->patch(route('admin.dj.gigs.requests-mode', $this->gig), ['requests_mode' => 'scheduled'])->assertSessionHasErrors('requests_mode');
});

test('booth mode shows the same queue full screen, and a gig with no requests is empty', function () {
    queuedRequest($this);
    $quiet = Gig::factory()->create();

    $this->get(route('admin.dj.requests.booth', ['gig' => $this->gig->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/requests/Booth')
            ->has('requests.pending', 1)
        );

    $this->get(route('admin.dj.requests.index', ['gig' => $quiet->id]))
        ->assertInertia(fn (Assert $page) => $page->has('requests.pending', 0));
});

test('with no gig at all, the queue says so', function () {
    Gig::query()->delete();

    $this->get(route('admin.dj.requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('gig', null)->where('requests', null));
});

test('the queue needs the manage requests permission and the DJ module, and is in the DJ menu', function () {
    $item = queuedRequest($this);

    $this->get(route('admin.dj.requests.index'))->assertOk();
    expect(djMenuHasRequests($this->user))->toBeTrue();

    $other = $this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]);
    $this->actingAs($other);

    $this->get(route('admin.dj.requests.index'))->assertForbidden();
    $this->get(route('admin.dj.requests.booth'))->assertForbidden();
    $this->patch(route('admin.dj.requests.status', $item), ['status' => 'accepted'])->assertForbidden();
    $this->patch(route('admin.dj.gigs.requests-mode', $this->gig), ['requests_mode' => 'closed'])->assertForbidden();
    expect(djMenuHasRequests($other))->toBeFalse();

    $this->actingAs($this->user);
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.dj.requests.index'))->assertNotFound();
});

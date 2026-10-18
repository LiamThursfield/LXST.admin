<?php

use App\Enums\Dj\SetlistSongType;
use App\Enums\TenantModule;
use App\Http\Middleware\AuthenticateGuestPass;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\GuestPass;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Dj\GuestPasses\GuestTokens;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->setlist = SongList::factory()->create(['name' => 'Wedding']);
    $this->pass = GuestPass::factory()->for($this->setlist)->create(['label' => 'Family', 'password' => 'maple-river-42']);

    RateLimiter::clear('dj-guest-pass:'.tenant()->getTenantKey().':'.$this->setlist->id.':127.0.0.1');
});

/**
 * The headers for a guest signed in with the pass
 *
 * @return array<string, string>
 */
function guestHeaders(GuestPass $pass): array
{
    return [AuthenticateGuestPass::HEADER => app(GuestTokens::class)->issue($pass)['token']];
}

test('a password is exchanged for a guest token, ignoring case and spaces', function () {
    $this->postJson(route('api.v1.dj.song-lists.guest-token.store', $this->setlist), ['password' => ' Maple-River-42 '])
        ->assertCreated()
        ->assertJsonPath('data.pass.label', 'Family')
        ->assertJsonPath('data.pass.can_add', true)
        ->assertJsonPath('data.list.name', 'Wedding')
        ->assertJsonStructure(['data' => ['token', 'expires_at']]);

    expect($this->pass->fresh()->last_used_at)->not->toBeNull();
});

test('wrong, revoked and expired passwords are refused, and repeated wrong ones lock the guest out', function () {
    config(['dj.guest_passes.max_attempts' => 3]);
    GuestPass::factory()->for($this->setlist)->revoked()->create(['password' => 'old-revoked-11']);
    GuestPass::factory()->for($this->setlist)->expired()->create(['password' => 'old-expired-12']);
    // Another list's pass doesn't work here
    GuestPass::factory()->create(['password' => 'other-list-13']);

    foreach (['old-revoked-11', 'old-expired-12', 'other-list-13'] as $password) {
        $this->postJson(route('api.v1.dj.song-lists.guest-token.store', $this->setlist), ['password' => $password])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => __('dj.guest_passes.api.wrong_password')]);
    }

    // Locked out, even with the right password
    $this->postJson(route('api.v1.dj.song-lists.guest-token.store', $this->setlist), ['password' => 'maple-river-42'])
        ->assertTooManyRequests();
});

test('a guest sees the setlist, with who added each song and which are theirs', function () {
    $mine = SongListItem::factory()->for($this->setlist)->create(['guest_pass_id' => $this->pass->id, 'guest_name' => 'Aunt Sue']);
    SongListItem::factory()->for($this->setlist)->custom('Bicep', 'Glue')->create(['added_by_user_id' => $this->createUserWithPermissions([])->id, 'type' => SetlistSongType::DoNotPlay]);

    $this->getJson(route('api.v1.dj.setlists.show', $this->setlist), guestHeaders($this->pass))
        ->assertOk()
        ->assertJsonPath('data.name', 'Wedding')
        ->assertJsonPath('data.pass.label', 'Family')
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $mine->id)
        ->assertJsonPath('data.items.0.added_by', 'Family · Aunt Sue')
        ->assertJsonPath('data.items.0.is_mine', true)
        // Users' names aren't shown to guests
        ->assertJsonPath('data.items.1.added_by', null)
        ->assertJsonPath('data.items.1.is_mine', false)
        ->assertJsonPath('data.items.1.title', 'Glue');
});

test('guests need a valid token for the list, and lose access once the pass is revoked', function () {
    $this->getJson(route('api.v1.dj.setlists.show', $this->setlist))->assertUnauthorized();
    $this->getJson(route('api.v1.dj.setlists.show', $this->setlist), [AuthenticateGuestPass::HEADER => 'nonsense'])->assertUnauthorized();
    $this->getJson(route('api.v1.dj.setlists.show', SongList::factory()->create()), guestHeaders($this->pass))->assertUnauthorized();

    $headers = guestHeaders($this->pass);
    $this->pass->update(['revoked_at' => now()]);

    $this->getJson(route('api.v1.dj.setlists.show', $this->setlist), $headers)->assertUnauthorized();

    // Tokens run out too
    $this->pass->update(['revoked_at' => null]);
    $headers = guestHeaders($this->pass);
    $this->travel((int) config('dj.guest_passes.token_minutes') + 1)->minutes();

    $this->getJson(route('api.v1.dj.setlists.show', $this->setlist), $headers)->assertUnauthorized();
});

test('guests search the library, leaving out songs hidden from requests', function () {
    $artist = CanonicalArtist::factory()->create(['name' => 'Bicep']);
    $glue = CanonicalTrack::factory()->for($artist, 'artist')->create(['title' => 'Glue']);
    $hidden = CanonicalTrack::factory()->for($artist, 'artist')->create(['title' => 'Glue (DJ Tool)', 'hidden_from_requests_at' => now()]);
    LibraryTrack::factory()->create(['canonical_track_id' => $glue->id]);
    LibraryTrack::factory()->create(['canonical_track_id' => $hidden->id]);

    $this->getJson(route('api.v1.dj.setlists.songs', ['songList' => $this->setlist, 'search' => 'bicep']), guestHeaders($this->pass))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Glue');
});

test('guests add songs, recorded with their pass and name, and change or remove only their own', function () {
    $song = CanonicalTrack::factory()->create();
    LibraryTrack::factory()->create(['canonical_track_id' => $song->id]);
    $headers = guestHeaders($this->pass);

    $this->postJson(route('api.v1.dj.setlists.items.store', $this->setlist), [
        'canonical_track_id' => $song->id,
        'type' => 'must_play',
        'note' => 'First dance',
        'guest_name' => 'Aunt Sue',
    ], $headers)->assertCreated()->assertJsonPath('data.is_mine', true);

    $item = SongListItem::sole();

    expect($item->guest_pass_id)->toBe($this->pass->id)
        ->and($item->guest_name)->toBe('Aunt Sue')
        ->and($item->added_by_user_id)->toBeNull();

    $this->patchJson(route('api.v1.dj.setlists.items.update', [$this->setlist, $item]), ['type' => 'maybe', 'note' => null], $headers)
        ->assertOk()
        ->assertJsonPath('data.type', 'maybe');

    $theirs = SongListItem::factory()->for($this->setlist)->create(['guest_pass_id' => GuestPass::factory()->for($this->setlist)->create()->id]);
    $elsewhere = SongListItem::factory()->create(['guest_pass_id' => $this->pass->id]);

    $this->patchJson(route('api.v1.dj.setlists.items.update', [$this->setlist, $theirs]), ['type' => 'maybe'], $headers)->assertForbidden();
    $this->deleteJson(route('api.v1.dj.setlists.items.destroy', [$this->setlist, $theirs]), [], $headers)->assertForbidden();
    $this->deleteJson(route('api.v1.dj.setlists.items.destroy', [$this->setlist, $elsewhere]), [], $headers)->assertNotFound();

    $this->deleteJson(route('api.v1.dj.setlists.items.destroy', [$this->setlist, $item]), [], $headers)->assertNoContent();

    expect(SongListItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

test('adding is validated, refused for hidden songs, and off for view-only passes', function () {
    $hidden = CanonicalTrack::factory()->create(['hidden_from_requests_at' => now()]);
    LibraryTrack::factory()->create(['canonical_track_id' => $hidden->id]);
    $headers = guestHeaders($this->pass);

    $this->postJson(route('api.v1.dj.setlists.items.store', $this->setlist), ['type' => 'often'], $headers)
        ->assertJsonValidationErrors(['custom_artist', 'type']);
    $this->postJson(route('api.v1.dj.setlists.items.store', $this->setlist), ['canonical_track_id' => $hidden->id, 'type' => 'maybe'], $headers)
        ->assertJsonValidationErrors('canonical_track_id');
    $this->postJson(route('api.v1.dj.setlists.items.store', $this->setlist), ['custom_artist' => 'Bicep', 'type' => 'maybe'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.is_custom', true);

    $viewOnly = GuestPass::factory()->for($this->setlist)->viewOnly()->create();

    $this->postJson(route('api.v1.dj.setlists.items.store', $this->setlist), ['custom_artist' => 'Bicep', 'type' => 'maybe'], guestHeaders($viewOnly))
        ->assertForbidden();
});

test('a gig\'s requests list has no guest setlist, and the API needs the DJ module', function () {
    $requests = SongList::factory()->requests()->forGig()->create();
    $pass = GuestPass::factory()->for($requests)->create(['password' => 'party-time-77']);

    $this->postJson(route('api.v1.dj.song-lists.guest-token.store', $requests), ['password' => 'party-time-77'])->assertCreated();
    $this->getJson(route('api.v1.dj.setlists.show', $requests), guestHeaders($pass))->assertNotFound();

    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->postJson(route('api.v1.dj.song-lists.guest-token.store', $this->setlist), ['password' => 'maple-river-42'])->assertNotFound();
});

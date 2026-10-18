<?php

use App\Data\Dj\GigRequestSettings;
use App\Enums\Dj\RequestsMode;
use App\Enums\Dj\RequestStatus;
use App\Enums\Dj\SetlistSongType;
use App\Enums\Dj\SongListKind;
use App\Enums\TenantModule;
use App\Http\Middleware\AuthenticateGuestPass;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Crm\FormSpamCheck;
use App\Services\Dj\GuestPasses\GuestTokens;
use Illuminate\Testing\TestResponse;

const SONG_REQUEST_BROWSER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

beforeEach(function () {
    config(['dj.requests.rate_limit_per_minute' => 10000, 'dj.requests.min_seconds' => 0]);

    $this->gig = Gig::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
        'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open, max_requests_per_visitor: 0, max_pending_requests: 0),
    ]);
    $this->song = CanonicalTrack::factory()->for(CanonicalArtist::factory()->create(['name' => 'Bicep', 'match_key' => 'bicep']), 'artist')
        ->create(['title' => 'Glue', 'match_key' => 'glue']);
    LibraryTrack::factory()->create(['canonical_track_id' => $this->song->id]);
});

/**
 * Requests a song for the gig, as a visitor's browser
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function requestSong(mixed $test, array $data, string $ip = '203.0.113.10', array $headers = []): TestResponse
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(route('api.v1.dj.gigs.requests.store', $test->gig->public_id), $data, ['User-Agent' => SONG_REQUEST_BROWSER_AGENT, ...$headers]);
}

test('a library song request goes on the gig\'s requests list, pending, with the requester\'s details', function () {
    requestSong($this, ['canonical_track_id' => $this->song->id, 'requester_name' => 'Sam', 'message' => 'For my mum'])
        ->assertCreated()
        ->assertJsonPath('data.outcome', 'accepted')
        ->assertJsonPath('data.message', __('dj.requests.outcomes.accepted'))
        ->assertJsonPath('data.request.title', 'Glue')
        ->assertJsonPath('data.request.artist', 'Bicep')
        ->assertJsonPath('data.request.status', 'pending')
        ->assertJsonPath('data.request.votes', 1);

    $item = SongListItem::sole();

    expect($item->songList->kind)->toBe(SongListKind::Requests)
        ->and($item->songList->gig_id)->toBe($this->gig->id)
        ->and($item->status)->toBe(RequestStatus::Pending)
        ->and($item->type)->toBeNull()
        ->and($item->requester_name)->toBe('Sam')
        ->and($item->message)->toBe('For my mum')
        ->and($item->visitor_hash)->not->toBeNull()
        ->and($item->votes()->count())->toBe(1);
});

test('a custom request matching a library song becomes it, unless that song is hidden', function () {
    requestSong($this, ['custom_artist' => 'BICEP', 'custom_title' => 'glue!'])->assertCreated();

    expect(SongListItem::sole())->canonical_track_id->toBe($this->song->id)->custom_artist->toBeNull();

    $hidden = CanonicalTrack::factory()->for($this->song->artist, 'artist')->create(['title' => 'Atlas', 'match_key' => 'atlas', 'hidden_from_requests_at' => now()]);
    LibraryTrack::factory()->create(['canonical_track_id' => $hidden->id]);

    requestSong($this, ['custom_artist' => 'Bicep', 'custom_title' => 'Atlas'])->assertCreated();

    expect(SongListItem::query()->latest('id')->first())
        ->canonical_track_id->toBeNull()
        ->custom_artist->toBe('Bicep')
        ->custom_title->toBe('Atlas');
});

test('the same song again becomes an upvote, once per visitor, until it\'s played', function () {
    requestSong($this, ['canonical_track_id' => $this->song->id], '203.0.113.10')->assertCreated();

    requestSong($this, ['canonical_track_id' => $this->song->id], '203.0.113.20')
        ->assertOk()
        ->assertJsonPath('data.outcome', 'upvoted')
        ->assertJsonPath('data.request.votes', 2);

    requestSong($this, ['canonical_track_id' => $this->song->id], '203.0.113.20')
        ->assertOk()
        ->assertJsonPath('data.outcome', 'already_requested')
        ->assertJsonPath('data.request.votes', 2);

    // Custom duplicates match once normalised
    requestSong($this, ['custom_artist' => 'Daft Punk', 'custom_title' => 'One More Time'])->assertCreated();
    requestSong($this, ['custom_artist' => 'daft punk', 'custom_title' => 'One more time.'], '203.0.113.30')
        ->assertJsonPath('data.outcome', 'upvoted');

    expect(SongListItem::count())->toBe(2);

    SongListItem::query()->where('canonical_track_id', $this->song->id)->update(['status' => RequestStatus::Played]);

    requestSong($this, ['canonical_track_id' => $this->song->id], '203.0.113.20')->assertCreated();

    expect(SongListItem::count())->toBe(3);
});

test('requests are refused when closed, custom requests are off, or a limit is reached', function () {
    $this->gig->update(['request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Closed)]);

    requestSong($this, ['canonical_track_id' => $this->song->id])
        ->assertForbidden()
        ->assertJsonPath('reason', 'requests_closed');

    $this->gig->update(['request_settings' => new GigRequestSettings(
        requests_mode: RequestsMode::Open,
        allow_custom_requests: false,
        max_requests_per_visitor: 1,
        max_pending_requests: 2,
    )]);

    requestSong($this, ['custom_artist' => 'Daft Punk'])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'custom_not_allowed');

    $other = CanonicalTrack::factory()->create();
    LibraryTrack::factory()->create(['canonical_track_id' => $other->id]);
    $third = CanonicalTrack::factory()->create();
    LibraryTrack::factory()->create(['canonical_track_id' => $third->id]);

    requestSong($this, ['canonical_track_id' => $this->song->id], '203.0.113.10')->assertCreated();
    requestSong($this, ['canonical_track_id' => $other->id], '203.0.113.10')
        ->assertTooManyRequests()
        ->assertJsonPath('reason', 'visitor_limit');

    // Upvotes don't count towards the visitor's limit
    requestSong($this, ['canonical_track_id' => $this->song->id], '203.0.113.20')->assertJsonPath('data.outcome', 'upvoted');

    requestSong($this, ['canonical_track_id' => $other->id], '203.0.113.30')->assertCreated();
    requestSong($this, ['canonical_track_id' => $third->id], '203.0.113.40')
        ->assertTooManyRequests()
        ->assertJsonPath('reason', 'queue_full');
});

test('requests need a guest pass when the gig\'s requests have one, and record it', function () {
    $list = SongList::factory()->requests()->forGig($this->gig)->create();
    $pass = GuestPass::factory()->for($list)->create(['label' => 'Guests']);

    requestSong($this, ['canonical_track_id' => $this->song->id])
        ->assertUnauthorized()
        ->assertJsonPath('reason', 'pass_required');

    // A pass for another list doesn't work
    $otherToken = app(GuestTokens::class)->issue(GuestPass::factory()->create())['token'];
    requestSong($this, ['canonical_track_id' => $this->song->id], headers: [AuthenticateGuestPass::HEADER => $otherToken])
        ->assertUnauthorized();

    $token = app(GuestTokens::class)->issue($pass)['token'];
    requestSong($this, ['canonical_track_id' => $this->song->id], headers: [AuthenticateGuestPass::HEADER => $token])->assertCreated();

    expect(SongListItem::sole())->guest_pass_id->toBe($pass->id)
        ->and($pass->fresh()->last_used_at)->not->toBeNull();
});

test('a request for a song the gig\'s setlist says not to play is accepted, but marked', function () {
    $setlist = SongList::factory()->forGig($this->gig)->create();
    SongListItem::factory()->for($setlist)->create(['canonical_track_id' => $this->song->id, 'type' => SetlistSongType::DoNotPlay]);
    SongListItem::factory()->for($setlist)->custom('Daft Punk', 'One More Time')->create(['type' => SetlistSongType::DoNotPlay]);

    requestSong($this, ['canonical_track_id' => $this->song->id])->assertCreated();
    requestSong($this, ['custom_artist' => 'Daft Punk', 'custom_title' => 'one more time'])->assertCreated();

    $other = CanonicalTrack::factory()->create();
    LibraryTrack::factory()->create(['canonical_track_id' => $other->id]);
    requestSong($this, ['canonical_track_id' => $other->id])->assertCreated();

    expect(SongListItem::query()->whereNotNull('status')->orderBy('id')->pluck('is_do_not_play')->all())->toBe([true, true, false]);
});

test('spam looks accepted but is not saved', function () {
    config(['dj.requests.min_seconds' => 60]);

    // The honeypot, a token that's too new, a tampered token, and a bot
    requestSong($this, ['canonical_track_id' => $this->song->id, 'website_url' => 'https://spam.test'])
        ->assertCreated()
        ->assertJsonPath('data.outcome', 'accepted')
        ->assertJsonPath('data.request.title', 'Glue');
    requestSong($this, ['custom_artist' => 'Spam', 'request_token' => FormSpamCheck::for('dj.requests')->issueToken()])->assertCreated();
    requestSong($this, ['custom_artist' => 'Spam', 'request_token' => 'tampered'])->assertCreated();
    requestSong($this, ['custom_artist' => 'Spam'], headers: ['User-Agent' => 'curl/8.0'])->assertCreated();

    expect(SongListItem::count())->toBe(0);
});

test('the gig details give the request form\'s spam protection, fresh on every call', function () {
    $first = $this->getJson(route('api.v1.dj.gigs.show', $this->gig->public_id))
        ->assertJsonPath('data.requests.spam_protection.honeypot_field', 'website_url')
        ->assertJsonPath('data.requests.spam_protection.token_field', 'request_token')
        ->json('data.requests.spam_protection.token');

    $this->travel(2)->seconds();

    expect($this->getJson(route('api.v1.dj.gigs.show', $this->gig->public_id))->json('data.requests.spam_protection.token'))
        ->not->toBe($first);
});

test('requests are validated, rate limited per gig, and need the DJ module', function () {
    requestSong($this, ['canonical_track_id' => $this->song->id, 'custom_artist' => 'Bicep'])
        ->assertJsonValidationErrors('custom_artist');
    requestSong($this, ['message' => str_repeat('a', 501)])
        ->assertJsonValidationErrors(['custom_artist', 'message']);

    $hidden = CanonicalTrack::factory()->create(['hidden_from_requests_at' => now()]);
    LibraryTrack::factory()->create(['canonical_track_id' => $hidden->id]);
    requestSong($this, ['canonical_track_id' => $hidden->id])->assertJsonValidationErrors('canonical_track_id');

    config(['dj.requests.rate_limit_per_minute' => 1]);
    requestSong($this, ['custom_artist' => 'One'], '198.51.100.1')->assertCreated();
    requestSong($this, ['custom_artist' => 'Two'], '198.51.100.1')->assertTooManyRequests();

    tenant()->update(['modules' => [TenantModule::Cms]]);
    requestSong($this, ['custom_artist' => 'Three'], '198.51.100.2')->assertNotFound();
});

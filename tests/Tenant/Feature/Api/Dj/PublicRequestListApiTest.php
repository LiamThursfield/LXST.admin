<?php

use App\Data\Dj\GigRequestSettings;
use App\Enums\Api\ContentScope;
use App\Enums\Dj\RequestsMode;
use App\Enums\Dj\RequestStatus;
use App\Enums\TenantModule;
use App\Http\Middleware\AuthenticateGuestPass;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Models\Dj\SongListItemVote;
use App\Services\Cms\Api\ContentVersion;
use App\Services\Cms\Api\VisitorPrivacy;
use App\Services\Dj\GuestPasses\GuestTokens;
use Illuminate\Testing\TestResponse;

const REQUEST_LIST_BROWSER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Safari/605.1.15';

beforeEach(function () {
    config(['dj.requests.upvotes_per_minute' => 10000]);

    $this->gig = Gig::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
        'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open, show_public_list: true, allow_upvotes: true, show_pass_labels: false),
    ]);
    $this->list = SongList::factory()->requests()->forGig($this->gig)->create();
});

/**
 * A request on the gig's list, with the given number of votes
 *
 * @param  array<string, mixed>  $attributes
 */
function listedRequest(mixed $test, int $votes = 1, array $attributes = []): SongListItem
{
    $item = SongListItem::factory()->for($test->list)->custom(fake()->name(), fake()->words(2, true))->create([
        'type' => null,
        'status' => RequestStatus::Pending,
        ...$attributes,
    ]);

    foreach (range(1, $votes) as $vote) {
        $item->votes()->create(['visitor_hash' => fake()->sha256()]);
    }

    return $item;
}

/**
 * Calls the API as a visitor's browser
 *
 * @param  array<string, string>  $headers
 */
function asVisitor(mixed $test, string $method, string $url, string $ip = '203.0.113.10', array $headers = []): TestResponse
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])
        ->json($method, $url, [], ['User-Agent' => REQUEST_LIST_BROWSER_AGENT, ...$headers]);
}

function visitorHashFor(Gig $gig, string $ip = '203.0.113.10'): string
{
    return (string) app(VisitorPrivacy::class)->visitorHash($ip, REQUEST_LIST_BROWSER_AGENT, $gig->starts_at);
}

test('the list gives pending and accepted requests by votes, and the latest played, without private details', function () {
    $few = listedRequest($this, 1, ['requester_name' => 'Hidden Name', 'message' => 'Secret message']);
    $many = listedRequest($this, 3, ['status' => RequestStatus::Accepted, 'requester_name' => 'Sam', 'show_requester_name' => true]);
    listedRequest($this, 5, ['status' => RequestStatus::Declined]);
    $played = listedRequest($this, 2, ['status' => RequestStatus::Played, 'played_at' => now()->subMinutes(5)]);

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id))
        ->assertOk()
        ->assertJsonPath('data.allow_upvotes', true)
        ->assertJsonCount(2, 'data.requests')
        ->assertJsonPath('data.requests.0.id', $many->id)
        ->assertJsonPath('data.requests.0.votes', 3)
        ->assertJsonPath('data.requests.0.status', 'accepted')
        ->assertJsonPath('data.requests.0.requester_name', 'Sam')
        ->assertJsonPath('data.requests.1.id', $few->id)
        ->assertJsonPath('data.requests.1.requester_name', null)
        ->assertJsonPath('data.requests.1.voted', false)
        ->assertJsonCount(1, 'data.played')
        ->assertJsonPath('data.played.0.id', $played->id)
        ->assertJsonPath('data.played.0.played_at', $played->played_at->toIso8601String())
        ->assertDontSee('Hidden Name')
        ->assertDontSee('Secret message');
});

test('the list says which requests the visitor voted for, and pass labels only when the gig says so', function () {
    $pass = GuestPass::factory()->for($this->list)->create(['label' => 'Bride']);
    $item = listedRequest($this, 1, ['guest_pass_id' => $pass->id]);
    $item->votes()->create(['visitor_hash' => visitorHashFor($this->gig)]);
    $token = app(GuestTokens::class)->issue($pass)['token'];

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id), headers: [AuthenticateGuestPass::HEADER => $token])
        ->assertJsonPath('data.requests.0.voted', true)
        ->assertJsonPath('data.requests.0.pass', null);

    $this->gig->update(['request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open, show_pass_labels: true)]);
    // As saving the gig in the admin would
    ContentVersion::for(ContentScope::Dj)->bump();

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id), '203.0.113.99', [AuthenticateGuestPass::HEADER => $token])
        ->assertJsonPath('data.requests.0.pass', 'Bride')
        ->assertJsonPath('data.requests.0.voted', false);
});

test('a visitor upvotes a request once, and the list shows it straight away', function () {
    $item = listedRequest($this, 1);
    $url = route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $item]);

    // Cache the list first
    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id))->assertJsonPath('data.requests.0.votes', 1);

    asVisitor($this, 'POST', $url)
        ->assertOk()
        ->assertJsonPath('data.outcome', 'voted')
        ->assertJsonPath('data.request.votes', 2);

    asVisitor($this, 'POST', $url)
        ->assertOk()
        ->assertJsonPath('data.outcome', 'already_voted')
        ->assertJsonPath('data.request.votes', 2);

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id))
        ->assertJsonPath('data.requests.0.votes', 2)
        ->assertJsonPath('data.requests.0.voted', true);

    expect(SongListItemVote::query()->where('visitor_hash', visitorHashFor($this->gig))->count())->toBe(1);
});

test('bots get an upvoted response without a vote', function () {
    $item = listedRequest($this, 1);

    $this->postJson(route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $item]), [], ['User-Agent' => 'curl/8.0'])
        ->assertOk()
        ->assertJsonPath('data.outcome', 'voted');

    expect($item->votes()->count())->toBe(1);
});

test('the gig\'s settings can hide the list and turn upvotes off, and closed requests can\'t be upvoted', function () {
    $item = listedRequest($this, 1);
    $played = listedRequest($this, 1, ['status' => RequestStatus::Played]);

    asVisitor($this, 'POST', route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $played]))
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'not_votable');

    $this->gig->update(['request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open, show_public_list: false, allow_upvotes: false)]);

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id))
        ->assertForbidden()
        ->assertJsonPath('reason', 'list_hidden');
    asVisitor($this, 'POST', route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $item]))
        ->assertForbidden()
        ->assertJsonPath('reason', 'upvotes_off');

    $this->gig->update(['request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Closed, allow_upvotes: true)]);

    asVisitor($this, 'POST', route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $item]))
        ->assertForbidden()
        ->assertJsonPath('reason', 'requests_closed');
});

test('a gig whose requests need a pass needs one for the list and upvotes too', function () {
    $item = listedRequest($this, 1);
    $pass = GuestPass::factory()->for($this->list)->create();

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id))
        ->assertUnauthorized()
        ->assertJsonPath('reason', 'pass_required');
    asVisitor($this, 'POST', route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $item]))->assertUnauthorized();

    $headers = [AuthenticateGuestPass::HEADER => app(GuestTokens::class)->issue($pass)['token']];

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id), headers: $headers)->assertOk();
    asVisitor($this, 'POST', route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $item]), headers: $headers)->assertOk();

    expect($item->votes()->latest('id')->value('guest_pass_id'))->toBe($pass->id);
});

test('a gig with no requests has an empty list, other gigs\' requests are not found, and the DJ module is needed', function () {
    $other = Gig::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $other->public_id))
        ->assertOk()
        ->assertJsonPath('data.requests', [])
        ->assertJsonPath('data.played', []);

    $item = listedRequest($this, 1);
    asVisitor($this, 'POST', route('api.v1.dj.gigs.requests.upvote', [$other->public_id, $item]))->assertNotFound();

    tenant()->update(['modules' => [TenantModule::Cms]]);

    asVisitor($this, 'GET', route('api.v1.dj.gigs.requests.index', $this->gig->public_id))->assertNotFound();
});

test('upvotes are rate limited per visitor and gig', function () {
    config(['dj.requests.upvotes_per_minute' => 1]);
    $item = listedRequest($this, 1);
    $url = route('api.v1.dj.gigs.requests.upvote', [$this->gig->public_id, $item]);

    asVisitor($this, 'POST', $url, '198.51.100.5')->assertOk();
    asVisitor($this, 'POST', $url, '198.51.100.5')->assertTooManyRequests();
});

test('a requester can opt in to their name showing', function () {
    $this->postJson(route('api.v1.dj.gigs.requests.store', $this->gig->public_id), [
        'custom_artist' => 'Robyn',
        'custom_title' => 'Dancing On My Own',
        'requester_name' => 'Sam',
        'show_name' => true,
    ], ['User-Agent' => REQUEST_LIST_BROWSER_AGENT])->assertCreated();

    expect(SongListItem::query()->where('custom_artist', 'Robyn')->value('show_requester_name'))->toBeTrue();
});

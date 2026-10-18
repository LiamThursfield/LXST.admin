<?php

use App\Data\Dj\GigRequestSettings;
use App\Enums\Api\ContentScope;
use App\Enums\Dj\RequestsMode;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\SongList;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Cms\Api\ContentVersion;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-05 20:00', 'UTC'));
    config(['dj.api.search_per_minute' => 10000]);
});

/**
 * A gig on now, open for requests
 *
 * @param  array<string, mixed>  $attributes
 */
function gigOnNow(array $attributes = []): Gig
{
    return Gig::factory()->create([
        'name' => 'Wedding',
        'venue' => 'The Barn',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(3),
        'timezone' => 'Europe/London',
        'notes' => 'Private: gate code 1234',
        'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open),
        ...$attributes,
    ]);
}

/**
 * A requestable song, still in a library
 */
function requestableSong(string $title, string $artist): CanonicalTrack
{
    $song = CanonicalTrack::factory()->for(CanonicalArtist::factory()->create(['name' => $artist]), 'artist')->create(['title' => $title, 'duration' => 269]);
    LibraryTrack::factory()->create(['canonical_track_id' => $song->id, 'location' => '/Users/dj/Music/'.$title.'.mp3']);

    return $song;
}

test('the current gig gives its public details and request settings, never private fields', function () {
    $gig = gigOnNow();

    $this->getJson(route('api.v1.dj.gigs.current'))
        ->assertOk()
        ->assertJsonPath('data.id', $gig->public_id)
        ->assertJsonPath('data.name', 'Wedding')
        ->assertJsonPath('data.venue', 'The Barn')
        ->assertJsonPath('data.timezone', 'Europe/London')
        ->assertJsonPath('data.is_on_now', true)
        ->assertJsonPath('data.requests.open', true)
        ->assertJsonPath('data.requests.mode', 'open')
        ->assertJsonPath('data.requests.requires_pass', false)
        ->assertJsonStructure(['data' => ['requests' => ['allow_custom_requests', 'max_requests_per_visitor', 'show_public_list', 'allow_upvotes']]])
        ->assertJsonMissingPath('data.notes')
        ->assertJsonMissingPath('data.requests.max_pending_requests')
        ->assertDontSee('gate code');
});

test('the current gig is null when there is none, and the next one when nothing is on', function () {
    $this->getJson(route('api.v1.dj.gigs.current'))->assertOk()->assertExactJson(['data' => null]);

    $next = Gig::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(4)]);
    Gig::factory()->create(['starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4)]);
    // Admin changes refresh the cached response
    ContentVersion::for(ContentScope::Dj)->bump();

    $this->getJson(route('api.v1.dj.gigs.current'))
        ->assertJsonPath('data.id', $next->public_id)
        ->assertJsonPath('data.is_on_now', false);
});

test('a gig is fetched by its public ID, past gigs show with requests closed, and numeric IDs are not found', function () {
    $past = Gig::factory()->create([
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->subWeek()->addHours(4),
        'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Scheduled, requests_open_minutes_before: 60),
    ]);

    $this->getJson(route('api.v1.dj.gigs.show', $past->public_id))
        ->assertOk()
        ->assertJsonPath('data.requests.open', false)
        ->assertJsonPath('data.requests.mode', 'scheduled')
        ->assertJsonPath('data.requests.opens_at', $past->starts_at->subHour()->toIso8601String())
        ->assertJsonPath('data.requests.closes_at', $past->ends_at->toIso8601String());

    $this->getJson('/api/v1/dj/gigs/'.$past->id)->assertNotFound();
    $this->getJson('/api/v1/dj/gigs/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
});

test('a gig with active request passes says a pass is needed', function () {
    $gig = gigOnNow();
    $requests = SongList::factory()->requests()->forGig($gig)->create();
    GuestPass::factory()->for($requests)->revoked()->create();

    $this->getJson(route('api.v1.dj.gigs.show', $gig->public_id))->assertJsonPath('data.requests.requires_pass', false);

    GuestPass::factory()->for($requests)->create();
    ContentVersion::for(ContentScope::Dj)->bump();

    $this->getJson(route('api.v1.dj.gigs.show', $gig->public_id))->assertJsonPath('data.requests.requires_pass', true);
});

test('library search gives requestable songs by title or artist, without file paths or hidden songs', function () {
    gigOnNow();
    requestableSong('Glue', 'Bicep');
    requestableSong('Atlas', 'Bicep');
    requestableSong('Like Glue', 'Sean Paul');
    $hidden = requestableSong('Glue (DJ Tool)', 'Bicep');
    $hidden->update(['hidden_from_requests_at' => now()]);
    // Not in any library any more
    CanonicalTrack::factory()->create(['title' => 'Glue (Old)']);

    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'glue']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        // Titles starting with the search come first
        ->assertJsonPath('data.0.title', 'Glue')
        ->assertJsonPath('data.0.artist', 'Bicep')
        ->assertJsonPath('data.0.duration', '4:29')
        ->assertJsonPath('data.1.title', 'Like Glue')
        ->assertDontSee('/Users/dj/Music', false);

    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'bicep']))->assertJsonCount(2, 'data');
});

test('search needs a search term, and a gig whose requests are open', function () {
    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'glue']))
        ->assertForbidden()
        ->assertJsonPath('message', __('dj.api.no_gig'));

    $closed = gigOnNow(['request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Closed)]);

    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'glue']))
        ->assertForbidden()
        ->assertJsonPath('message', __('dj.api.requests_closed'));

    // A given gig is checked instead of the current one
    $open = Gig::factory()->create([
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(4),
        'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open),
    ]);

    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'glue', 'gig' => $open->public_id]))->assertOk();
    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'glue', 'gig' => $closed->public_id]))->assertForbidden();

    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'g', 'gig' => 'nope']))
        ->assertJsonValidationErrors(['search', 'gig']);
});

test('search is rate limited, and the DJ API needs the DJ module', function () {
    config(['dj.api.search_per_minute' => 2]);
    gigOnNow();

    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'one']))->assertOk();
    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'two']))->assertOk();
    $this->getJson(route('api.v1.dj.tracks.index', ['search' => 'three']))->assertTooManyRequests();

    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->getJson(route('api.v1.dj.gigs.current'))->assertNotFound();
});

test('a DJ admin change refreshes the DJ API\'s cached responses at once, but not the CMS API\'s', function () {
    $gig = gigOnNow();
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]));

    $this->getJson(route('api.v1.dj.gigs.current'))->assertJsonPath('data.name', 'Wedding');
    $cmsVersion = ContentVersion::for(ContentScope::Cms)->current();

    $this->put(route('admin.dj.gigs.update', $gig), [
        'name' => 'Wedding Reception',
        'starts_at' => $gig->starts_at->setTimezone('Europe/London')->format('Y-m-d\TH:i'),
        'ends_at' => $gig->ends_at->setTimezone('Europe/London')->format('Y-m-d\TH:i'),
        'timezone' => 'Europe/London',
    ])->assertSessionHasNoErrors();

    $this->getJson(route('api.v1.dj.gigs.current'))->assertJsonPath('data.name', 'Wedding Reception');

    // Only the DJ API's responses refresh
    expect(ContentVersion::for(ContentScope::Cms)->current())->toBe($cmsVersion);
});

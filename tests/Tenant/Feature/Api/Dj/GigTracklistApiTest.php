<?php

use App\Data\Dj\GigRequestSettings;
use App\Enums\Api\ApiTokenAbility;
use App\Enums\Dj\HistorySource;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\LibraryTrack;
use App\Services\Authorisation\Enums\SettingsPermission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 22:00', 'UTC'));

    $this->gig = Gig::factory()->create([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addHours(2),
        'request_settings' => new GigRequestSettings(show_tracklist: true, tracklist_per_page: 2, tracklist_delay_minutes: 0),
    ]);
    $this->session = HistorySession::factory()->create(['gig_id' => $this->gig->id, 'source' => HistorySource::Cli, 'library_id' => null]);
});

/**
 * A play in the gig's session, the given minutes ago
 */
function gigPlay(mixed $test, int $minutesAgo, string $title, ?string $artist = null, array $attributes = []): HistoryPlay
{
    return HistoryPlay::factory()->for($test->session, 'session')->create([
        'library_track_id' => null,
        'source_track_id' => null,
        'title' => $title,
        'artist' => $artist,
        'played_at' => now()->subMinutes($minutesAgo),
        ...$attributes,
    ]);
}

test('the tracklist gives what\'s playing now and what played before it, newest first, a page at a time', function () {
    gigPlay($this, 30, 'First');
    gigPlay($this, 20, 'Second');
    gigPlay($this, 4, 'Third', 'Robyn');
    // Another gig's play isn't listed
    HistoryPlay::factory()->create(['played_at' => now()->subMinute()]);

    $this->getJson(route('api.v1.dj.gigs.tracklist', $this->gig->public_id))
        ->assertOk()
        ->assertJsonPath('now_playing.title', 'Third')
        ->assertJsonPath('now_playing.artist', 'Robyn')
        ->assertJsonPath('gig.id', $this->gig->public_id)
        ->assertJsonPath('data.0.title', 'Third')
        ->assertJsonPath('data.1.title', 'Second')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson(route('api.v1.dj.gigs.tracklist', ['gig' => $this->gig->public_id, 'page' => 2]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'First');

    $this->getJson(route('api.v1.dj.gigs.current.tracklist'))->assertJsonPath('now_playing.title', 'Third');
});

test('library and song plays use their names, and nothing is playing once the latest track is old', function () {
    $song = CanonicalTrack::factory()->for(CanonicalArtist::factory()->create(['name' => 'Bicep']), 'artist')->create(['title' => 'Glue']);
    gigPlay($this, 40, 'ignored', 'ignored', ['library_track_id' => LibraryTrack::factory()->create(['title' => 'Atlas'])->id]);
    gigPlay($this, 30, 'ignored', 'ignored', ['canonical_track_id' => $song->id]);

    $this->getJson(route('api.v1.dj.gigs.tracklist', $this->gig->public_id))
        ->assertJsonPath('now_playing', null)
        ->assertJsonPath('data.0.title', 'Glue')
        ->assertJsonPath('data.0.artist', 'Bicep')
        ->assertJsonPath('data.1.title', 'Atlas');
});

test('the delay holds tracks back from the tracklist', function () {
    $this->gig->update(['request_settings' => new GigRequestSettings(show_tracklist: true, tracklist_delay_minutes: 10)]);
    gigPlay($this, 20, 'Older');
    gigPlay($this, 3, 'Just now');

    $this->getJson(route('api.v1.dj.gigs.tracklist', $this->gig->public_id))
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Older')
        // Held back by the delay, it still counts as playing for a while after it shows
        ->assertJsonPath('now_playing.title', 'Older');
});

test('the gig details include what\'s playing now, refreshed by a new play', function () {
    gigPlay($this, 4, 'Third');

    $this->getJson(route('api.v1.dj.gigs.current'))
        ->assertJsonPath('data.tracklist.enabled', true)
        ->assertJsonPath('data.tracklist.now_playing.title', 'Third');

    $owner = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens]);
    $token = $owner->createToken('CLI', [ApiTokenAbility::DjNowPlaying->value])->plainTextToken;
    $this->withToken($token)->postJson(route('api.v1.dj.now-playing.store'), ['artist' => 'Robyn', 'title' => 'Dancing On My Own'])->assertCreated();
    Auth::forgetGuards();

    $this->withToken('')->getJson(route('api.v1.dj.gigs.current'))
        ->assertJsonPath('data.tracklist.now_playing.title', 'Dancing On My Own');
});

test('the tracklist is hidden unless the gig shows it, which it doesn\'t by default', function () {
    $this->gig->update(['request_settings' => null]);

    $this->getJson(route('api.v1.dj.gigs.tracklist', $this->gig->public_id))
        ->assertForbidden()
        ->assertJsonPath('reason', 'tracklist_hidden');

    $this->getJson(route('api.v1.dj.gigs.show', $this->gig->public_id))
        ->assertJsonPath('data.tracklist.enabled', false)
        ->assertJsonPath('data.tracklist.now_playing', null);
});

test('the tracklist is validated, needs a gig, and needs the DJ module', function () {
    $this->getJson(route('api.v1.dj.gigs.tracklist', ['gig' => $this->gig->public_id, 'page' => 0]))->assertJsonValidationErrors('page');

    Gig::query()->delete();
    $this->getJson(route('api.v1.dj.gigs.current.tracklist'))->assertNotFound();

    tenant()->update(['modules' => [TenantModule::Cms]]);
    $this->getJson(route('api.v1.dj.gigs.current.tracklist'))->assertNotFound();
});

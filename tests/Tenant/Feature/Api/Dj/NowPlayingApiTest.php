<?php

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Dj\HistorySource;
use App\Enums\Dj\RequestStatus;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-19 22:00', 'UTC'));

    $owner = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens]);
    $this->token = $owner->createToken('CLI', [ApiTokenAbility::DjNowPlaying->value])->plainTextToken;

    $this->gig = Gig::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3)]);

    $this->library = Library::factory()->create();
    $this->song = CanonicalTrack::factory()->for(CanonicalArtist::factory()->create(['name' => 'Bicep', 'match_key' => 'bicep']), 'artist')
        ->create(['title' => 'Glue', 'match_key' => 'glue']);
    $this->track = LibraryTrack::factory()->for($this->library)->create(['source_track_id' => '170878528', 'canonical_track_id' => $this->song->id, 'title' => 'Glue']);
});

/**
 * Reports a play with the CLI's token
 *
 * @param  array<string, mixed>  $data
 */
function reportPlay(mixed $test, array $data, ?string $token = null): TestResponse
{
    // Each request should authenticate afresh, as a real one would
    Auth::forgetGuards();

    return $test->withToken($token ?? $test->token)->postJson(route('api.v1.dj.now-playing.store'), $data);
}

test('a reported track matched by its source ID goes in the gig\'s live session', function () {
    reportPlay($this, ['source_track_id' => '170878528', 'library_id' => $this->library->id, 'artist' => 'Bicep', 'title' => 'Glue', 'deck' => 2, 'played_at' => now()->subMinutes(4)->toIso8601String()])
        ->assertCreated()
        ->assertJsonPath('data.outcome', 'recorded')
        ->assertJsonPath('data.gig.id', $this->gig->public_id)
        ->assertJsonPath('data.play.matched', 'library_track');

    $session = HistorySession::sole();
    $play = HistoryPlay::sole();

    expect($session->gig_id)->toBe($this->gig->id)
        ->and($session->source)->toBe(HistorySource::Cli)
        ->and($session->library_id)->toBeNull()
        ->and($play->library_track_id)->toBe($this->track->id)
        ->and($play->canonical_track_id)->toBe($this->song->id)
        ->and($play->played_at->toIso8601String())->toBe(now()->subMinutes(4)->toIso8601String());

    // A second track goes in the same session, after the first
    // Left out, the time it was played is now
    reportPlay($this, ['artist' => 'Daft Punk', 'title' => 'One More Time'])
        ->assertCreated()
        ->assertJsonPath('data.play.matched', 'none');

    expect(HistorySession::count())->toBe(1)
        ->and(HistoryPlay::query()->latest('id')->first())
        ->position->toBe(2)
        ->artist->toBe('Daft Punk')
        ->title->toBe('One More Time')
        ->and($session->fresh()->ended_at->toIso8601String())->toBe(now()->toIso8601String());
});

test('without a source ID, a track matches its song by normalised artist and title', function () {
    reportPlay($this, ['artist' => 'BICEP', 'title' => 'glue!'])
        ->assertCreated()
        ->assertJsonPath('data.play.matched', 'song');

    expect(HistoryPlay::sole())->canonical_track_id->toBe($this->song->id)->library_track_id->toBeNull();
});

test('the same track again within a few minutes is a duplicate, but later it counts again', function () {
    reportPlay($this, ['source_track_id' => '170878528', 'title' => 'Glue', 'played_at' => now()->subMinutes(35)->toIso8601String()])->assertCreated();

    reportPlay($this, ['source_track_id' => '170878528', 'title' => 'Glue', 'played_at' => now()->subMinutes(33)->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.outcome', 'duplicate');

    // Unmatched tracks are compared by artist and title
    reportPlay($this, ['artist' => 'Robyn', 'title' => 'Dancing On My Own'])->assertCreated();
    reportPlay($this, ['artist' => 'robyn', 'title' => 'Dancing on my own'])->assertJsonPath('data.outcome', 'duplicate');

    reportPlay($this, ['source_track_id' => '170878528', 'title' => 'Glue'])->assertCreated();

    expect(HistoryPlay::count())->toBe(3);
});

test('requests for the song are marked played, by library song or by custom artist and title', function () {
    $list = SongList::factory()->requests()->forGig($this->gig)->create();
    $library = SongListItem::factory()->for($list)->create(['type' => null, 'status' => RequestStatus::Accepted, 'canonical_track_id' => $this->song->id]);
    $custom = SongListItem::factory()->for($list)->custom('Bicep', 'Glue')->create(['type' => null, 'status' => RequestStatus::Pending]);
    $declined = SongListItem::factory()->for($list)->create(['type' => null, 'status' => RequestStatus::Declined, 'canonical_track_id' => $this->song->id]);
    $other = SongListItem::factory()->for($list)->custom('Daft Punk', 'One More Time')->create(['type' => null, 'status' => RequestStatus::Pending]);

    reportPlay($this, ['source_track_id' => '170878528', 'artist' => 'Bicep', 'title' => 'Glue'])
        ->assertJsonPath('data.requests_played', 2);

    expect($library->fresh())->status->toBe(RequestStatus::Played)->played_at->not->toBeNull()
        ->and($custom->fresh()->status)->toBe(RequestStatus::Played)
        ->and($declined->fresh()->status)->toBe(RequestStatus::Declined)
        ->and($other->fresh()->status)->toBe(RequestStatus::Pending);

    // An unmatched play still marks a custom request with the same name
    reportPlay($this, ['artist' => 'daft punk', 'title' => 'One More Time'])->assertJsonPath('data.requests_played', 1);

    expect($other->fresh()->status)->toBe(RequestStatus::Played);
});

test('reports are refused without a gig on, but a pinned gig or a given one takes them', function () {
    $this->gig->delete();
    $later = Gig::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(4)]);

    // The current gig is only up next, so it isn't live
    reportPlay($this, ['title' => 'Glue'])
        ->assertStatus(409)
        ->assertJsonPath('reason', 'no_gig');

    reportPlay($this, ['title' => 'Glue', 'gig' => $later->public_id])->assertCreated();

    $later->update(['is_pinned' => true]);
    reportPlay($this, ['title' => 'Atlas'])
        ->assertCreated()
        ->assertJsonPath('data.gig.id', $later->public_id);

    expect(HistoryPlay::count())->toBe(2);
});

test('the gig\'s played tracks show unmatched plays by their reported artist and title', function () {
    reportPlay($this, ['artist' => 'Daft Punk', 'title' => 'One More Time'])->assertCreated();

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]));

    $this->get(route('admin.dj.gigs.edit', $this->gig))
        ->assertInertia(fn ($page) => $page
            ->where('played.data.0.title', 'One More Time')
            ->where('played.data.0.artist', 'Daft Punk')
        );
});

test('reports are validated', function () {
    reportPlay($this, ['title' => '', 'library_id' => 999, 'played_at' => now()->addHour()->toIso8601String(), 'gig' => 'nope', 'deck' => 9])
        ->assertJsonValidationErrors(['title', 'library_id', 'played_at', 'gig', 'deck']);

    reportPlay($this, ['title' => 'Glue', 'played_at' => now()->subDays(2)->toIso8601String()])
        ->assertJsonValidationErrors('played_at');
});

test('reports need a token with the now-playing ability, and the DJ module', function () {
    $owner = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens]);
    $syncOnly = $owner->createToken('Sync', [ApiTokenAbility::DjSync->value])->plainTextToken;

    reportPlay($this, ['title' => 'Glue'], 'lxst_nope')->assertUnauthorized();
    reportPlay($this, ['title' => 'Glue'], $syncOnly)->assertForbidden();

    tenant()->update(['modules' => [TenantModule::Cms]]);

    reportPlay($this, ['title' => 'Glue'])->assertNotFound();

    expect(HistoryPlay::count())->toBe(0);
});

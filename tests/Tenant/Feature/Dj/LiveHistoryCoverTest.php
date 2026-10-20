<?php

use App\Data\Dj\GigRequestSettings;
use App\Enums\Api\ApiTokenAbility;
use App\Enums\Dj\HistorySource;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\LibraryTrack;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Dj\LiveHistoryCover;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 23:00', 'UTC'));
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]));

    $this->gig = Gig::factory()->create([
        'starts_at' => now()->subHours(3),
        'ends_at' => now()->addHour(),
        'request_settings' => new GigRequestSettings(show_tracklist: true),
    ]);
    $this->live = sessionWithPlays($this->gig, HistorySource::Cli, ['Live one', 'Live two'], now()->subHours(2));
});

/**
 * A session with a play for each title, a few minutes apart from the given start
 *
 * @param  list<string>  $titles
 */
function sessionWithPlays(?Gig $gig, HistorySource $source, array $titles, CarbonImmutable $start): HistorySession
{
    $session = HistorySession::factory()->create([
        'gig_id' => $gig?->id,
        'source' => $source,
        'library_id' => null,
        'started_at' => $start,
        'ended_at' => $start->addMinutes(4 * (count($titles) - 1)),
    ]);

    foreach ($titles as $index => $title) {
        HistoryPlay::factory()->for($session, 'session')->create([
            'library_track_id' => $source === HistorySource::Import ? LibraryTrack::factory()->create(['title' => $title])->id : null,
            'source_track_id' => null,
            'title' => $source === HistorySource::Import ? null : $title,
            'position' => $index + 1,
            'played_at' => $start->addMinutes(4 * $index),
        ]);
    }

    return $session;
}

/**
 * The titles on the gig's admin page, in order
 *
 * @return list<string>
 */
function gigPageTitles(mixed $test): array
{
    $titles = [];

    $test->get(route('admin.dj.gigs.edit', $test->gig))
        ->assertInertia(function (Assert $page) use (&$titles): void {
            $titles = collect($page->toArray()['props']['played']['data'])->pluck('title')->all();
        });

    return $titles;
}

test('linking an import that covers the live session hides the live plays, and unlinking brings them back', function () {
    $import = sessionWithPlays(null, HistorySource::Import, ['Imported one', 'Imported two'], now()->subHours(2)->addMinutes(2));

    expect(gigPageTitles($this))->toBe(['Live one', 'Live two']);

    $this->post(route('admin.dj.history.bulk.link'), ['session_ids' => [$import->id], 'gig_id' => $this->gig->id])->assertRedirect();

    expect($this->live->fresh()->is_superseded)->toBeTrue()
        ->and(gigPageTitles($this))->toBe(['Imported one', 'Imported two']);

    $this->get(route('admin.dj.gigs.edit', $this->gig))
        ->assertInertia(fn (Assert $page) => $page
            ->where('linkedSessions.data', fn ($sessions) => collect($sessions)->firstWhere('id', $this->live->id)['is_superseded'] === true)
        );

    $this->post(route('admin.dj.history.bulk.unlink'), ['session_ids' => [$import->id]])->assertRedirect();

    expect($this->live->fresh()->is_superseded)->toBeFalse()
        ->and(gigPageTitles($this))->toBe(['Live one', 'Live two']);
});

test('an import at another time on the gig doesn\'t hide the live plays', function () {
    $earlier = sessionWithPlays(null, HistorySource::Import, ['Soundcheck'], now()->subHours(2)->subMinutes(30));

    $this->post(route('admin.dj.history.bulk.link'), ['session_ids' => [$earlier->id], 'gig_id' => $this->gig->id]);

    expect($this->live->fresh()->is_superseded)->toBeFalse()
        ->and(gigPageTitles($this))->toBe(['Soundcheck', 'Live one', 'Live two']);
});

test('the public tracklist and song pages leave out covered live plays', function () {
    $import = sessionWithPlays($this->gig, HistorySource::Import, ['Imported one'], now()->subHours(2)->addMinutes(9));
    app(LiveHistoryCover::class)->refresh([$this->gig->id]);

    expect($this->live->fresh()->is_superseded)->toBeTrue();

    $this->getJson(route('api.v1.dj.gigs.tracklist', $this->gig->public_id))
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Imported one');

    // Within the 10 minutes of slack, but past it the live session isn't covered
    $import->update(['started_at' => now()->subHours(2)->addMinutes(25), 'ended_at' => now()->subHours(2)->addMinutes(25)]);
    app(LiveHistoryCover::class)->refresh([$this->gig->id]);

    expect($this->live->fresh()->is_superseded)->toBeFalse();
});

test('a live play into a time an import already covers is hidden as it arrives', function () {
    sessionWithPlays($this->gig, HistorySource::Import, ['Imported one', 'Imported two'], now()->subMinutes(10));
    $owner = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens]);
    $token = $owner->createToken('CLI', [ApiTokenAbility::DjNowPlaying->value])->plainTextToken;
    $this->live->delete();

    Auth::forgetGuards();
    $this->withToken($token)->postJson(route('api.v1.dj.now-playing.store'), ['title' => 'Late live play'])->assertCreated();

    expect(HistorySession::query()->where('source', HistorySource::Cli)->sole()->is_superseded)->toBeTrue();
});

test('the History page says when a suggested gig already has live plays', function () {
    $import = sessionWithPlays(null, HistorySource::Import, ['Imported one', 'Imported two', 'Imported three', 'Imported four', 'Imported five'], now()->subHours(2));

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]))
        ->get(route('admin.dj.history.index'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('suggestedLinks', fn ($links) => collect($links)->firstWhere('session.id', $import->id)['gig']['has_live_plays'] === true)
        ));
});

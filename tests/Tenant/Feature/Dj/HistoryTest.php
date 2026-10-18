<?php

use App\Enums\Dj\HistorySource;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Settings\DjSettings;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Dj\HistoryLinker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]);
    $this->actingAs($this->user);
});

/**
 * A session running for the given minutes from the given time (UTC)
 */
function historySession(string $startedAt, int $minutes = 60, array $attributes = []): HistorySession
{
    $start = CarbonImmutable::parse($startedAt, 'UTC');

    return HistorySession::factory()->create([
        'started_at' => $start,
        'ended_at' => $start->addMinutes($minutes),
        ...$attributes,
    ]);
}

test('sessions are listed with their gig, and can be filtered by link and stats', function () {
    $gig = Gig::factory()->create(['name' => 'Warehouse']);
    historySession('2026-09-01 20:00', attributes: ['name' => 'HISTORY linked', 'gig_id' => $gig->id]);
    historySession('2026-09-02 20:00', attributes: ['name' => 'HISTORY practice', 'include_in_stats' => false]);

    $this->get(route('admin.dj.history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/history/Index')
            ->has('sessions.data', 2)
            ->where('canManage', true)
            ->missing('suggestedGigs')
        );

    $this->get(route('admin.dj.history.index', ['filter' => ['linked' => 'linked']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.gig.name', 'Warehouse')
        );

    $this->get(route('admin.dj.history.index', ['filter' => ['stats' => 'excluded']]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.name', 'HISTORY practice')
        );
});

test('sessions can be linked to a gig, unlinked, and left out of stats', function () {
    $gig = Gig::factory()->create();
    $sessions = new Collection([historySession('2026-09-01 20:00'), historySession('2026-09-01 22:00')]);
    $ids = $sessions->modelKeys();

    $this->post(route('admin.dj.history.bulk.link'), ['session_ids' => $ids, 'gig_id' => $gig->id])
        ->assertRedirect();

    expect(HistorySession::query()->where('gig_id', $gig->id)->count())->toBe(2);

    $this->post(route('admin.dj.history.bulk.unlink'), ['session_ids' => [$ids[0]]])->assertRedirect();
    $this->post(route('admin.dj.history.bulk.stats'), ['session_ids' => $ids, 'include_in_stats' => false])->assertRedirect();

    expect(HistorySession::find($ids[0]))->gig_id->toBeNull()->include_in_stats->toBeFalse()
        ->and(HistorySession::find($ids[1]))->gig_id->toBe($gig->id)->include_in_stats->toBeFalse();
});

test('bulk actions only take the fields they need', function () {
    $session = historySession('2026-09-01 20:00');

    $this->post(route('admin.dj.history.bulk.link'), ['session_ids' => [$session->id, 999]])
        ->assertSessionHasErrors(['gig_id', 'session_ids.1']);

    $this->post(route('admin.dj.history.bulk.unlink'), ['session_ids' => [$session->id], 'gig_id' => 1])
        ->assertSessionHasErrors('gig_id');

    $this->post(route('admin.dj.history.bulk.stats'), ['session_ids' => []])
        ->assertSessionHasErrors(['session_ids', 'include_in_stats']);
});

test('a gig can be created from sessions, running from the first to the last', function () {
    $sessions = new Collection([historySession('2026-09-01 20:00', 90), historySession('2026-09-01 22:00', 120)]);

    $this->post(route('admin.dj.history.bulk.create-gig'), ['session_ids' => $sessions->modelKeys()])
        ->assertRedirect(route('admin.dj.gigs.edit', Gig::sole()));

    $gig = Gig::sole();

    expect($gig->starts_at->toIso8601String())->toBe('2026-09-01T20:00:00+00:00')
        ->and($gig->ends_at->toIso8601String())->toBe('2026-09-02T00:00:00+00:00')
        ->and($gig->timezone)->toBe(app(DjSettings::class)->default_timezone)
        ->and($gig->name)->toStartWith('Set on ')
        ->and($gig->historySessions()->count())->toBe(2);

    $this->post(route('admin.dj.history.bulk.create-gig'), ['session_ids' => [$sessions->first()->id], 'name' => 'Birthday'])
        ->assertRedirect();

    expect(Gig::query()->latest('id')->value('name'))->toBe('Birthday');
});

test('sessions close together are suggested as one gig, and those during a gig as links to it', function () {
    config(['dj.history.gig_gap_minutes' => 60]);

    // A long session, then a relaunch 30 minutes after it ended: one gig
    $first = historySession('2026-08-01 20:00', 180);
    $relaunch = historySession('2026-08-01 23:30', 60);
    // A day later, on its own
    $nextDay = historySession('2026-08-02 20:00', 60);
    // Already linked, or left out of stats: not suggested
    historySession('2026-08-03 20:00', attributes: ['gig_id' => Gig::factory()->create(['starts_at' => '2026-07-01 20:00', 'ends_at' => '2026-07-01 23:00'])->id]);
    historySession('2026-08-04 20:00', attributes: ['include_in_stats' => false]);
    // During a gig: suggested as a link, not a new gig
    $gig = Gig::factory()->create(['starts_at' => '2026-08-05 19:00', 'ends_at' => '2026-08-05 23:00']);
    $during = historySession('2026-08-05 20:00');

    $linker = app(HistoryLinker::class);

    expect($linker->suggestedGigs()->map->modelKeys()->all())->toBe([[$nextDay->id], [$first->id, $relaunch->id]])
        ->and($linker->suggestedLinks()->map(fn (array $suggestion) => [$suggestion['session']->id, $suggestion['gig']->id])->all())
        ->toBe([[$during->id, $gig->id]]);

    $this->get(route('admin.dj.history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('suggestedGigs', 2)
                ->where('suggestedGigs.1.session_ids', [$first->id, $relaunch->id])
                ->where('suggestedLinks.0.gig.id', $gig->id)
            )
        );
});

test('a gig shows the plays of its linked sessions in time order', function () {
    $gig = Gig::factory()->create();
    $later = historySession('2026-09-01 22:00', attributes: ['gig_id' => $gig->id]);
    $earlier = historySession('2026-09-01 20:00', attributes: ['gig_id' => $gig->id]);
    HistoryPlay::factory()->for($later, 'session')->create(['played_at' => '2026-09-01 22:10']);
    HistoryPlay::factory()->for($earlier, 'session')->create(['played_at' => '2026-09-01 20:10']);
    HistoryPlay::factory()->for(historySession('2026-09-01 20:00'), 'session')->create();

    $this->get(route('admin.dj.gigs.edit', $gig))
        ->assertInertia(fn (Assert $page) => $page
            ->has('played.data', 2)
            ->where('played.data.0.session', $earlier->name)
            ->where('played.data.0.is_manual', false)
            ->has('linkedSessions.data', 2)
        );
});

test('songs can be added to what a gig played by hand, and removed again', function () {
    $gig = Gig::factory()->create([
        'starts_at' => '2026-09-01 19:00',
        'ends_at' => '2026-09-01 23:00',
        'timezone' => 'Europe/London',
    ]);
    $song = CanonicalTrack::factory()->create();

    $this->post(route('admin.dj.gigs.plays.store', $gig), ['canonical_track_id' => $song->id, 'played_at' => '2026-09-01T21:30'])
        ->assertRedirect();

    $play = HistoryPlay::sole();
    $session = $play->session;

    expect($session->source)->toBe(HistorySource::Manual)
        ->and($session->gig_id)->toBe($gig->id)
        ->and($session->library_id)->toBeNull()
        ->and($play->canonical_track_id)->toBe($song->id)
        // 21:30 in London in September is 20:30 UTC
        ->and($play->played_at->toIso8601String())->toBe('2026-09-01T20:30:00+00:00')
        ->and($session->started_at->toIso8601String())->toBe('2026-09-01T20:30:00+00:00');

    $this->delete(route('admin.dj.gigs.plays.destroy', [$gig, $play]))->assertRedirect();

    expect(HistoryPlay::count())->toBe(0)
        ->and(HistorySession::count())->toBe(0);

    $this->post(route('admin.dj.gigs.plays.store', $gig), ['canonical_track_id' => 999, 'played_at' => 'soon'])
        ->assertSessionHasErrors(['canonical_track_id', 'played_at']);
});

test('imported plays cannot be removed from a gig, and plays from other gigs are not found', function () {
    $gig = Gig::factory()->create();
    $imported = HistoryPlay::factory()->for(historySession('2026-09-01 20:00', attributes: ['gig_id' => $gig->id]), 'session')->create();
    $elsewhere = HistoryPlay::factory()->for(historySession('2026-09-02 20:00'), 'session')->create();

    $this->delete(route('admin.dj.gigs.plays.destroy', [$gig, $imported]))->assertUnprocessable();
    $this->delete(route('admin.dj.gigs.plays.destroy', [$gig, $elsewhere]))->assertNotFound();

    expect(HistoryPlay::count())->toBe(2);
});

test('history can be viewed without managing gigs, but not changed', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs]));
    $session = historySession('2026-09-01 20:00');
    HistoryPlay::factory()->for($session, 'session')->count(2)->create();

    $this->get(route('admin.dj.history.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->get(route('admin.dj.history.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/history/Show')
            ->has('plays.data', 2)
        );

    $this->post(route('admin.dj.history.bulk.unlink'), ['session_ids' => [$session->id]])->assertForbidden();
    $this->post(route('admin.dj.gigs.plays.store', Gig::factory()->create()), [])->assertForbidden();
});

test('history is not found without gig permissions or the DJ module', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));

    $this->get(route('admin.dj.history.index'))->assertForbidden();

    $this->actingAs($this->user);
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.dj.history.index'))->assertNotFound();
});

<?php

use App\Enums\Dj\HistorySource;
use App\Enums\Dj\MusicalKey;
use App\Enums\Dj\RequestStatus;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Authorisation\Enums\DjPermission;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 23:30', 'UTC'));
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewStats]));

    $this->gig = Gig::factory()->create(['starts_at' => '2026-10-21 20:00', 'ends_at' => '2026-10-22 01:00']);
    $this->session = HistorySession::factory()->create(['gig_id' => $this->gig->id, 'source' => HistorySource::Import, 'include_in_stats' => true]);
});

/**
 * A play of a library track with the given BPM and key, at the given time
 */
function statsPlay(HistorySession $session, string $at, float $bpm, MusicalKey $key, string $title = 'Track'): HistoryPlay
{
    return HistoryPlay::factory()->for($session, 'session')->create([
        'library_track_id' => LibraryTrack::factory()->create(['title' => $title, 'bpm' => $bpm, 'key' => $key])->id,
        'played_at' => $at,
    ]);
}

test('the stats give the set\'s plays, length, BPM over time and keys', function () {
    statsPlay($this->session, '2026-10-21 21:00', 120, MusicalKey::from('8A'), 'Opener');
    statsPlay($this->session, '2026-10-21 21:30', 124, MusicalKey::from('8A'));
    statsPlay($this->session, '2026-10-21 22:45', 128, MusicalKey::from('9A'), 'Closer');
    // An unmatched live-style play counts, without a BPM
    HistoryPlay::factory()->for($this->session, 'session')->create(['library_track_id' => null, 'title' => 'White label', 'artist' => 'Unknown', 'played_at' => '2026-10-21 22:00']);

    $this->get(route('admin.dj.gigs.stats', $this->gig))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/gig/Stats')
            ->where('gig.is_live', true)
            ->where('stats.plays.count', 4)
            ->where('stats.plays.minutes', 105)
            ->has('stats.bpm.series', 3)
            ->where('stats.bpm.series.0.title', 'Opener')
            ->where('stats.bpm.average', 124)
            ->where('stats.bpm.min', 120)
            ->where('stats.bpm.max', 128)
            ->where('stats.keys.0.camelot', '8A')
            ->where('stats.keys.0.count', 2)
            ->where('stats.keys.1.camelot', '9A')
        );
});

test('sessions out of stats, and live plays an import covers, don\'t count', function () {
    statsPlay($this->session, '2026-10-21 21:00', 120, MusicalKey::from('8A'));
    $practice = HistorySession::factory()->create(['gig_id' => $this->gig->id, 'include_in_stats' => false]);
    statsPlay($practice, '2026-10-21 20:30', 100, MusicalKey::from('1A'));
    $covered = HistorySession::factory()->create(['gig_id' => $this->gig->id, 'source' => HistorySource::Cli, 'is_superseded' => true]);
    statsPlay($covered, '2026-10-21 21:00', 120, MusicalKey::from('8A'));

    $this->get(route('admin.dj.gigs.stats', $this->gig))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.plays.count', 1)
            ->has('stats.keys', 1)
        );
});

test('the stats show how requests went, the most requested, and custom requests to buy', function () {
    $list = SongList::factory()->requests()->forGig($this->gig)->create();
    $song = CanonicalTrack::factory()->create(['title' => 'Glue']);
    $request = fn (array $attributes, int $votes) => tap(
        SongListItem::factory()->for($list)->create(['type' => null, 'status' => RequestStatus::Pending, ...$attributes]),
        fn (SongListItem $item) => collect(range(1, $votes))->each(fn () => $item->votes()->create(['visitor_hash' => fake()->sha256()])),
    );

    $request(['canonical_track_id' => $song->id, 'status' => RequestStatus::Played], 4);
    $request(['canonical_track_id' => null, 'custom_artist' => 'Daft Punk', 'custom_title' => 'One More Time'], 2);
    $request(['canonical_track_id' => null, 'custom_artist' => 'daft punk', 'custom_title' => 'One more time.', 'status' => RequestStatus::Played], 1);
    $request(['canonical_track_id' => null, 'custom_artist' => 'Robyn', 'custom_title' => 'Dancing On My Own', 'status' => RequestStatus::Declined, 'is_do_not_play' => true], 1);

    $this->get(route('admin.dj.gigs.stats', $this->gig))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.requests.received', 4)
            ->where('stats.requests.votes', 8)
            ->where('stats.requests.statuses.played', 2)
            ->where('stats.requests.statuses.declined', 1)
            ->where('stats.requests.do_not_play', 1)
            ->where('stats.most_requested.0.title', 'Glue')
            ->where('stats.most_requested.0.votes', 4)
            ->has('stats.to_buy', 2)
            ->where('stats.to_buy.0.title', 'One More Time')
            ->where('stats.to_buy.0.count', 2)
            ->where('stats.to_buy.0.votes', 3)
            ->where('stats.to_buy.0.played', true)
        );
});

test('a gig with nothing played or requested has empty stats', function () {
    $this->get(route('admin.dj.gigs.stats', Gig::factory()->create()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('gig.is_live', false)
            ->where('stats.plays.count', 0)
            ->where('stats.plays.minutes', null)
            ->where('stats.bpm.average', null)
            ->where('stats.requests.received', 0)
            ->where('stats.to_buy', [])
        );
});

test('stats need the view stats permission and the DJ module, and gigs link to them', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs]));

    $this->get(route('admin.dj.gigs.stats', $this->gig))->assertForbidden();
    $this->get(route('admin.dj.gigs.edit', $this->gig))->assertInertia(fn (Assert $page) => $page->where('canViewStats', false));

    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewGigs, DjPermission::ManageGigs, DjPermission::ViewStats]));
    $this->get(route('admin.dj.gigs.edit', $this->gig))->assertInertia(fn (Assert $page) => $page->where('canViewStats', true));

    tenant()->update(['modules' => [TenantModule::Cms]]);
    $this->get(route('admin.dj.gigs.stats', $this->gig))->assertNotFound();
});

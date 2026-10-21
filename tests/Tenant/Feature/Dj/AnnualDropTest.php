<?php

use App\Enums\Dj\HistorySource;
use App\Enums\Dj\MusicalKey;
use App\Enums\Dj\RequestStatus;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Models\Settings\DjSettings;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\NavigationService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'UTC'));
    app(DjSettings::class)->fill(['default_timezone' => 'UTC'])->save();
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewStats]));

    $this->gig = Gig::factory()->create(['starts_at' => '2026-06-01 20:00', 'ends_at' => '2026-06-02 01:00']);
    $this->gigSession = HistorySession::factory()->create(['gig_id' => $this->gig->id, 'include_in_stats' => true]);
    $this->practice = HistorySession::factory()->create(['gig_id' => null, 'include_in_stats' => true]);

    $this->bicep = CanonicalArtist::factory()->create(['name' => 'Bicep']);
    $this->glue = CanonicalTrack::factory()->for($this->bicep, 'artist')->create(['title' => 'Glue', 'bpm' => 122, 'key' => MusicalKey::from('8A')]);
    $this->atlas = CanonicalTrack::factory()->for($this->bicep, 'artist')->create(['title' => 'Atlas', 'bpm' => 123, 'key' => MusicalKey::from('8A')]);
});

/**
 * A play of the song (through a library track) in the session, at the given time
 */
function dropPlay(HistorySession $session, CanonicalTrack $song, string $at): HistoryPlay
{
    return HistoryPlay::factory()->for($session, 'session')->create([
        'library_track_id' => LibraryTrack::factory()->create(['canonical_track_id' => $song->id, 'title' => $song->title, 'bpm' => $song->bpm, 'key' => $song->key])->id,
        'played_at' => $at,
    ]);
}

test('the drop gives a year\'s top artists and tracks, BPM, key, hours, gigs and requests, from gigs by default', function () {
    dropPlay($this->gigSession, $this->glue, '2026-06-01 21:00');
    dropPlay($this->gigSession, $this->glue, '2026-06-01 21:04');
    dropPlay($this->gigSession, $this->atlas, '2026-06-01 21:08');
    // An unmatched play, by its reported name
    HistoryPlay::factory()->for($this->gigSession, 'session')->create(['library_track_id' => null, 'artist' => 'Robyn', 'title' => 'Dancing On My Own', 'played_at' => '2026-06-01 21:12']);
    // Practice doesn't count with gigs only
    dropPlay($this->practice, $this->atlas, '2026-05-01 18:00');
    // Another year doesn't count
    dropPlay($this->gigSession, $this->atlas, '2025-06-01 21:00');
    $requests = SongList::factory()->requests()->forGig($this->gig)->create();
    SongListItem::factory()->for($requests)->count(3)->create(['type' => null, 'status' => RequestStatus::Pending]);

    $this->get(route('admin.dj.annual-drop', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/AnnualDrop')
            ->where('filter.year', 2026)
            ->where('filter.scope', 'gigs')
            ->where('years', [2026, 2025])
            ->where('drop.totals.plays', 4)
            ->where('drop.totals.songs', 3)
            ->where('drop.totals.gigs', 1)
            ->where('drop.totals.requests', 3)
            // 4 minutes between each of the first three, and the last counts as a full track (10 minutes)
            ->where('drop.totals.hours', round(22 / 60, 1))
            ->where('drop.top_artists.0.name', 'Bicep')
            ->where('drop.top_artists.0.plays', 3)
            ->where('drop.top_artists.1.name', 'Robyn')
            ->where('drop.top_tracks.0.title', 'Glue')
            ->where('drop.top_tracks.0.plays', 2)
            ->where('drop.bpm.most_played', 122)
            ->where('drop.bpm.range_from', 120)
            ->where('drop.bpm.range_to', 124)
            ->where('drop.bpm.range_count', 3)
            ->where('drop.key.camelot', '8A')
            ->where('drop.key.plays', 3)
        );
});

test('all history counts practice too, but not sessions out of stats or live plays an import covers', function () {
    dropPlay($this->gigSession, $this->glue, '2026-06-01 21:00');
    dropPlay($this->practice, $this->atlas, '2026-05-01 18:00');
    dropPlay(HistorySession::factory()->create(['include_in_stats' => false]), $this->atlas, '2026-05-02 18:00');
    dropPlay(HistorySession::factory()->create(['gig_id' => $this->gig->id, 'source' => HistorySource::Cli, 'is_superseded' => true]), $this->glue, '2026-06-01 21:00');

    $this->get(route('admin.dj.annual-drop', ['year' => 2026, 'scope' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter.scope', 'all')
            ->where('drop.totals.plays', 2)
            ->where('drop.totals.gigs', 1)
        );
});

test('a date range can be picked instead of a year, and is validated', function () {
    dropPlay($this->gigSession, $this->glue, '2026-06-01 21:00');
    dropPlay($this->gigSession, $this->atlas, '2026-07-01 21:00');

    $this->get(route('admin.dj.annual-drop', ['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter.year', null)
            ->where('filter.from', '2026-06-01')
            ->where('drop.totals.plays', 1)
            ->where('drop.top_tracks.0.title', 'Glue')
        );

    $this->get(route('admin.dj.annual-drop', ['from' => '2026-06-30', 'to' => '2026-06-01', 'scope' => 'everything', 'year' => 1999]))
        ->assertSessionHasErrors(['to', 'scope', 'year']);
});

test('this year is the default, and a year with nothing played says so', function () {
    $this->get(route('admin.dj.annual-drop'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filter.year', 2026)
            ->where('drop.totals.plays', 0)
            ->where('drop.top_artists', [])
            ->where('drop.bpm.most_played', null)
            ->where('drop.key', null)
        );
});

test('the drop needs the view stats permission and the DJ module, and is in the DJ menu', function () {
    $hasMenuItem = fn (mixed $user) => app(NavigationService::class)->resolveMenuForUser(TenantMenu::MENU_MAIN, $user)
        ->flatten()
        ->contains(fn (mixed $item) => $item instanceof MenuItem && $item->key === TenantMenu::MENU_MAIN_SECTION_DJ_ITEM_ANNUAL_DROP);

    expect($hasMenuItem(auth()->user()))->toBeTrue();

    $other = $this->createUserWithPermissions([DjPermission::ViewGigs]);
    $this->actingAs($other)->get(route('admin.dj.annual-drop'))->assertForbidden();
    expect($hasMenuItem($other))->toBeFalse();

    tenant()->update(['modules' => [TenantModule::Cms]]);
    $this->get(route('admin.dj.annual-drop'))->assertNotFound();
});

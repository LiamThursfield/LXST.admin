<?php

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\HistorySource;
use App\Enums\Dj\MergeAction;
use App\Enums\Dj\RequestStatus;
use App\Enums\Dj\SetlistSongType;
use App\Enums\Dj\SongListKind;
use App\Enums\Dj\SongListVisibility;
use App\Enums\TenantModule;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\MatchDismissal;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Models\Settings\DjSettings;
use App\Services\Authorisation\Enums\DjPermission;
use App\Services\Dj\CurrentGig;
use App\Services\Dj\HistoryLinker;
use App\Services\Dj\Matching\MatchSuggestions;
use Carbon\CarbonImmutable;
use Database\Seeders\DjSeeder;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 21:00', 'UTC'));
    tenant()->update(['modules' => TenantModule::cases()]);
    $this->seed(DjSeeder::class);

    $this->main = Library::query()->where('name', 'Rekordbox')->sole();
    $this->backup = Library::query()->where('name', 'USB Backup')->sole();
});

test('the DJ seeder imports two libraries sharing songs, with tags and an import log', function () {
    $shared = CanonicalTrack::query()
        ->whereHas('libraryTracks', fn ($query) => $query->where('library_id', $this->main->id))
        ->whereHas('libraryTracks', fn ($query) => $query->where('library_id', $this->backup->id))
        ->count();

    expect($this->main->tracks()->count())->toBe(200)
        ->and($this->backup->tracks()->count())->toBe(40)
        ->and($shared)->toBeGreaterThanOrEqual(20)
        ->and($this->main->tags()->count())->toBeGreaterThan(0)
        ->and(LibraryTrack::query()->has('tags')->count())->toBeGreaterThan(100)
        ->and(LibraryTrack::query()->whereNotNull('service')->exists())->toBeTrue()
        ->and(LibraryTrack::query()->where('location', 'like', '%/Music/Demo/%')->exists())->toBeTrue()
        ->and($this->main->imports()->sole()->counts['tracks_added'])->toBe(200);
});

test('the DJ seeder leaves match suggestions to review, and logs a dismissal, a merge and a split', function () {
    $suggestions = app(MatchSuggestions::class);

    expect($suggestions->tracks()->pluck('second.title')->merge($suggestions->tracks()->pluck('first.title')))
        ->toContain('Calm Down (DJ Afron Remx)')
        ->and($suggestions->artists()->flatMap(fn (array $pair) => [$pair['first']->name, $pair['second']->name]))
        ->toContain('Burna Boi')
        ->and(MatchDismissal::query()->where('record', CanonicalRecord::Track)->exists())->toBeTrue()
        ->and(CanonicalMerge::query()->where('action', MergeAction::Merge)->exists())->toBeTrue()
        ->and(CanonicalMerge::query()->where('action', MergeAction::Split)->exists())->toBeTrue();
});

test('the DJ seeder links requests to the hosted page, with the tracklist on', function () {
    $settings = app(DjSettings::class);

    expect($settings->request_page_url)->toBeNull()
        ->and($settings->hosted_page_enabled)->toBeTrue()
        ->and($settings->show_tracklist)->toBeTrue();
});

test('the DJ seeder hides songs, an artist and the backup library from requests', function () {
    expect(CanonicalTrack::query()->whereNotNull('hidden_from_requests_at')->count())->toBe(2)
        ->and(CanonicalArtist::query()->whereNotNull('hidden_from_requests_at')->exists())->toBeTrue()
        ->and($this->backup->hidden_from_requests_at)->not->toBeNull();
});

test('the DJ seeder makes past, live and upcoming gigs, with the live one pinned and playing', function () {
    $live = app(CurrentGig::class)->resolve();
    $wedding = Gig::query()->where('name', 'like', '%Wedding%')->sole();
    $nowPlaying = HistoryPlay::query()
        ->whereHas('session', fn ($query) => $query->where('gig_id', $live->id)->where('source', HistorySource::Cli))
        ->latest('played_at')
        ->first();

    expect($live->name)->toBe('Friday Late Session')
        ->and($live->is_pinned)->toBeTrue()
        ->and($live->isOnAt(now()))->toBeTrue()
        ->and($live->requestsOpenAt(now()))->toBeTrue()
        ->and($live->requestSettings()->show_tracklist)->toBeTrue()
        ->and($nowPlaying->played_at->greaterThan(now()->subMinutes(10)))->toBeTrue()
        ->and($wedding->starts_at->isFuture())->toBeTrue()
        ->and($wedding->requestsOpenAt(now()))->toBeFalse()
        ->and(Gig::query()->where('ends_at', '<', now())->count())->toBe(4);
});

test('the DJ seeder makes history: split at midnight, covered live plays, manual plays, practice and suggestions', function () {
    $rooftop = Gig::query()->where('name', 'Rooftop Summer Party')->sole();
    $practice = HistorySession::query()->where('source_history_id', 'like', 'demo-practice-%')->sole();

    expect($rooftop->historySessions()->where('source', HistorySource::Import)->count())->toBe(2)
        ->and($rooftop->historySessions()->where('source', HistorySource::Cli)->sole()->is_superseded)->toBeTrue()
        ->and($rooftop->historySessions()->where('source', HistorySource::Manual)->sole()->plays()->count())->toBe(2)
        ->and($practice->gig_id)->toBeNull()
        ->and($practice->include_in_stats)->toBeFalse()
        ->and(app(HistoryLinker::class)->suggestedLinks())->not->toBeEmpty()
        ->and(app(HistoryLinker::class)->suggestedGigs())->not->toBeEmpty();
});

test('the DJ seeder makes setlists and guest passes of every kind', function () {
    $setlists = SongList::query()->where('kind', SongListKind::Setlist)->get();
    $passes = GuestPass::query()->get();

    expect($setlists->pluck('visibility')->unique()->values()->all())->toEqualCanonicalizing(SongListVisibility::cases())
        ->and($setlists->whereNull('gig_id'))->not->toBeEmpty()
        ->and($setlists->whereNotNull('gig_id'))->not->toBeEmpty()
        ->and(SongListItem::query()->whereNotNull('type')->pluck('type')->unique()->values()->all())->toEqualCanonicalizing(SetlistSongType::cases())
        ->and(SongListItem::query()->whereNotNull('type')->whereNull('canonical_track_id')->exists())->toBeTrue()
        ->and($passes->filter->isActive()->where('can_add', true))->not->toBeEmpty()
        ->and($passes->filter->isActive()->where('can_add', false))->not->toBeEmpty()
        ->and($passes->filter->isExpired())->not->toBeEmpty()
        ->and($passes->filter->isRevoked())->not->toBeEmpty()
        ->and($passes->first()->matches(DjSeeder::GUEST_PASSWORD))->toBeTrue()
        ->and($passes->pluck('songList.kind')->unique()->values()->all())->toEqualCanonicalizing(SongListKind::cases());
});

test('the DJ seeder makes requests in every status, with votes, custom songs, a do not play and guest passes', function () {
    $requests = SongListItem::query()->whereNotNull('status')->get();

    expect($requests->pluck('status')->unique()->values()->all())->toEqualCanonicalizing(RequestStatus::cases())
        ->and($requests->where('is_do_not_play', true))->not->toBeEmpty()
        ->and($requests->whereNull('canonical_track_id'))->not->toBeEmpty()
        ->and($requests->whereNotNull('requester_name'))->not->toBeEmpty()
        ->and($requests->whereNotNull('message'))->not->toBeEmpty()
        ->and($requests->whereNotNull('guest_pass_id'))->not->toBeEmpty()
        ->and(SongListItem::query()->has('votes', '>', 3)->exists())->toBeTrue()
        // A custom request for a library song is matched to it
        ->and(SongListItem::query()->whereNotNull('status')->whereHas('song', fn ($query) => $query->where('title', 'Love Me JeJe'))->value('status'))->toBe(RequestStatus::Played);
});

test('the DJ admin pages load with the seeded data', function () {
    $this->actingAs($this->createUserWithPermissions(DjPermission::cases()));

    foreach (['libraries', 'tracks', 'artists', 'songs', 'canonical-artists', 'matching', 'history', 'gigs', 'setlists', 'requests', 'requests/booth', 'annual-drop'] as $page) {
        $this->get("/admin/dj/{$page}")->assertOk();
    }

    foreach (Gig::all() as $gig) {
        $this->get(route('admin.dj.gigs.edit', $gig))->assertOk();
        $this->get(route('admin.dj.gigs.stats', $gig))->assertOk();
    }
});

test('running the DJ seeder again creates nothing new', function () {
    $counts = fn () => [Library::count(), LibraryTrack::count(), Gig::count(), HistorySession::count(), HistoryPlay::count(), SongList::count(), SongListItem::count(), GuestPass::count()];
    $first = $counts();

    $this->seed(DjSeeder::class);

    expect($counts())->toBe($first);
});

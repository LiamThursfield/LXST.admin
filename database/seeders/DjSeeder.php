<?php

namespace Database\Seeders;

use App\Data\Dj\GigRequestSettings;
use App\Data\Dj\NowPlayingData;
use App\Data\Dj\SongRequestData;
use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Enums\Dj\RequestsMode;
use App\Enums\Dj\RequestStatus;
use App\Enums\Dj\SetlistSongType;
use App\Enums\Dj\SongListKind;
use App\Enums\Dj\SongListVisibility;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\Library;
use App\Models\Dj\SongList;
use App\Models\Settings\DjSettings;
use App\Models\User;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use App\Services\Dj\CurrentGig;
use App\Services\Dj\HistoryLinker;
use App\Services\Dj\Import\Data\HistorySessionData;
use App\Services\Dj\Import\Data\TrackData;
use App\Services\Dj\Import\LibraryImporter;
use App\Services\Dj\Import\RekordboxXml;
use App\Services\Dj\Matching\CanonicalMerger;
use App\Services\Dj\NowPlaying\NowPlayingRecorder;
use App\Services\Dj\Requests\RequestVotes;
use App\Services\Dj\Requests\SongRequester;
use App\Services\Dj\SongLists;
use Carbon\CarbonImmutable;
use Database\Seeders\Dj\DemoLibrarySource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * A DJ's libraries, history, gigs, setlists and requests, to test every DJ feature with (#167).
 * The libraries are bundled Rekordbox XML exports (real songs, made-up files and IDs), imported
 * through the real importer, so matching and history work as they do in production.
 *
 * - Gigs: a past one with its history and requests, a past private party whose requests needed a
 *   guest pass, one on now (pinned, requests open, now playing set) and an upcoming wedding
 * - Guest passes use the password GUEST_PASSWORD
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=DjSeeder`. Safe to re-run: the DJ
 * settings are updated in place, and nothing else is seeded when the tenant has any libraries or
 * gigs, so it never mixes with a real library.
 */
class DjSeeder extends Seeder
{
    /** The password of every seeded guest pass */
    public const string GUEST_PASSWORD = 'GuestPassword';

    /** The bundled exports, in database/seeders/data/dj */
    protected const string MAIN_XML = 'rekordbox-main.xml';

    protected const string BACKUP_XML = 'rekordbox-backup.xml';

    /**
     * Sets played from the main library: days ago, start time, number of plays and minutes between
     * them. Rekordbox keeps a history session per day, so sets past midnight are split in two.
     *
     * @var array<string, array{0: int, 1: string, 2: int, 3: int}>
     */
    protected const array SETS = [
        // Linked to the past gig, which a live session also covers. It starts late enough to pass
        // midnight however the gaps between plays fall.
        'rooftop' => [21, '22:00', 40, 4],
        // During the private party, left for its link suggestion
        'engagement' => [49, '19:30', 30, 5],
        // Unlinked and past midnight, so it's suggested as a new gig
        'afterparty' => [35, '22:40', 30, 4],
        // Made into gigs from their history
        'warehouse' => [77, '22:00', 35, 4],
        'garden' => [105, '15:00', 28, 5],
        // Unlinked sets, counted in stats over all history
        'bar-1' => [63, '20:00', 25, 5],
        'bar-2' => [91, '20:00', 25, 5],
        'bar-3' => [119, '21:00', 30, 4],
        // Too short to count in stats
        'practice' => [3, '15:00', 4, 4],
    ];

    protected string $timezone;

    protected ?User $admin;

    public function __construct(
        protected LibraryImporter $importer,
        protected CanonicalMerger $merger,
        protected HistoryLinker $linker,
        protected NowPlayingRecorder $nowPlaying,
        protected SongRequester $requester,
        protected RequestVotes $votes,
        protected SongLists $songLists,
        protected CurrentGig $currentGig,
        protected DjSettings $settings,
    ) {
        $this->timezone = $settings->default_timezone;
    }

    public function run(): void
    {
        $this->seedSettings();

        if (Library::query()->exists() || Gig::query()->exists()) {
            return;
        }

        $this->admin = RoleUserSeeder::userFor(RoleEnum::Admin);

        $mainXml = new RekordboxXml(database_path('seeders/data/dj/'.self::MAIN_XML));
        $main = $this->importLibrary('Rekordbox', self::MAIN_XML, new DemoLibrarySource($mainXml, $this->history($mainXml)));
        $backup = $this->importLibrary('USB Backup', self::BACKUP_XML, new DemoLibrarySource(new RekordboxXml(database_path('seeders/data/dj/'.self::BACKUP_XML))));

        $this->seedMatching($main, $backup);
        $this->seedHidden($main, $backup);

        $this->seedRooftopGig($main, $backup);
        $this->seedEngagementGig($main);
        $this->seedLiveGig($main);
        $this->seedWeddingGig($main);

        $this->seedHistoryGigs();
        $this->seedSetlists($main);

        $this->linker->setIncludeInStats($this->sessions('practice'), false);
    }

    /**
     * Request links use the hosted page (#123), with the tracklist on (#125). Updated in place, so
     * a re-run keeps them as seeded.
     */
    protected function seedSettings(): void
    {
        $this->settings->fill([
            'request_page_url' => null,
            'hosted_page_enabled' => true,
            'show_tracklist' => true,
        ])->save();
    }

    /**
     * Imports a bundled export, logging it as an upload
     */
    protected function importLibrary(string $name, string $file, DemoLibrarySource $source): Library
    {
        $library = Library::query()->create(['name' => $name, 'source' => LibrarySource::RekordboxXml]);
        $startedAt = CarbonImmutable::now();

        $counts = $this->importer->import($library, $source, 200);

        $library->imports()->create([
            'user_id' => $this->admin?->id,
            'source' => LibrarySource::RekordboxXml,
            'status' => ImportStatus::Completed,
            'original_filename' => $file,
            'total' => $source->trackCount(),
            'processed' => $source->trackCount(),
            'counts' => $counts,
            'started_at' => $startedAt,
            'finished_at' => CarbonImmutable::now(),
        ]);

        return $library;
    }

    /**
     * Play history for the main library's sets, ordered roughly by BPM as a set builds
     *
     * @return list<HistorySessionData>
     */
    protected function history(RekordboxXml $xml): array
    {
        /** @var list<TrackData> $tracks */
        $tracks = [];

        foreach ($xml->trackChunks(500) as $chunk) {
            array_push($tracks, ...$chunk);
        }

        $sessions = [];

        foreach (self::SETS as $set => [$daysAgo, $time, $count, $gap]) {
            $playedAt = $this->localTime($daysAgo, $time);
            $byDate = [];

            $picked = collect(fake()->randomElements($tracks, $count))
                ->sortBy(fn (TrackData $track) => ($track->bpm ?? 120) + fake()->numberBetween(-8, 8));

            foreach ($picked as $track) {
                $byDate[$playedAt->toDateString()][] = ['source_track_id' => $track->sourceTrackId, 'played_at' => $playedAt->utc()];
                $playedAt = $playedAt->addMinutes($gap)->addSeconds(fake()->numberBetween(-60, 60));
            }

            $part = 0;

            foreach ($byDate as $date => $plays) {
                $part++;
                $sessions[] = new HistorySessionData(
                    "demo-{$set}-{$part}",
                    "HISTORY {$date}",
                    array_values(array_map(fn (array $play, int $index) => [...$play, 'position' => $index + 1], $plays, array_keys($plays))),
                );
            }
        }

        return $sessions;
    }

    /**
     * Songs in both libraries are matched automatically. The backup also has near matches: a typo
     * left to review (a track and an artist), a different remix dismissed, a duplicate merged by
     * hand, and a clean edit split off the dirty one.
     */
    protected function seedMatching(Library $main, Library $backup): void
    {
        $this->merger->mergeTracks(
            $this->song($main, 'One Dance - Tall Boys Mona Lisa Edit'),
            $this->song($backup, 'One Dance (Tall Boys Mona Lisa Remix)'),
            $this->admin,
        );

        $this->merger->dismiss(
            CanonicalRecord::Track,
            $this->song($main, 'One Dance (Peace Control Remix)')->id,
            $this->song($backup, 'One Dance (Peace Control Remix II)')->id,
            $this->admin,
        );

        $this->merger->splitTrack($backup->tracks()->where('title', 'Ye (Intro - Clean)')->firstOrFail(), $this->admin);
    }

    /**
     * Songs, an artist and the backup library hidden from requests (#142)
     */
    protected function seedHidden(Library $main, Library $backup): void
    {
        $this->song($main, 'Bitch Better Have My Money')->update(['hidden_from_requests_at' => CarbonImmutable::now()]);
        $this->song($main, 'Sexy Grime (DJcity Intro - Dirty)')->update(['hidden_from_requests_at' => CarbonImmutable::now()]);
        CanonicalArtist::query()->where('name', 'Joyner Lucas')->update(['hidden_from_requests_at' => CarbonImmutable::now()]);
        $backup->update(['hidden_from_requests_at' => CarbonImmutable::now()]);
    }

    /**
     * Three weeks ago, with its imported history (split at midnight), a live session the import
     * covers (#159), plays added by hand, the tracklist on, and requests in every status
     */
    protected function seedRooftopGig(Library $main, Library $backup): Gig
    {
        $gig = Gig::query()->create([
            'name' => 'Rooftop Summer Party',
            'venue' => 'Skyline Rooftop',
            'location' => 'London',
            'starts_at' => $this->localTime(21, '21:00')->utc(),
            'ends_at' => $this->localTime(20, '02:00')->utc(),
            'timezone' => $this->timezone,
            'request_settings' => new GigRequestSettings(show_tracklist: true),
        ]);

        $this->linker->link($this->sessions('rooftop'), $gig);

        // The CLI tool reported the start of the set live, before the import replaced it
        $imported = HistoryPlay::query()
            ->whereIn('history_session_id', $this->sessions('rooftop')->modelKeys())
            ->with('track.artist')
            ->orderBy('played_at')
            ->limit(5)
            ->get();

        foreach ($imported as $play) {
            $this->nowPlaying->record($gig, new NowPlayingData(
                $play->source_track_id,
                $main->id,
                $play->track?->artist?->name,
                (string) $play->track?->title,
                CarbonImmutable::instance($play->played_at),
            ));
        }

        $this->linker->addManualPlay($gig, $this->song($backup, 'Drive'), $this->localTime(20, '00:50')->utc());
        $this->linker->addManualPlay($gig, $this->song($backup, 'Essence'), $this->localTime(20, '01:40')->utc());

        $list = $this->songLists->requestsListFor($gig);
        $at = fn (int $minutes) => $gig->starts_at->addMinutes($minutes);

        $this->request($list, $this->songOf($main, 'Not Like Us (DJcity Intro - Dirty)'), RequestStatus::Played, 6, $at(20), ['requester_name' => 'Priya', 'show_requester_name' => true]);
        $this->request($list, $this->songOf($main, 'Espresso (keyløud Afro House Remix)'), RequestStatus::Played, 3, $at(45));
        $this->request($list, ['custom_artist' => 'Tems', 'custom_title' => 'Free Mind'], RequestStatus::Played, 2, $at(70), ['message' => 'For my sister!']);
        $this->request($list, $this->songOf($main, 'Pink Pony Club (DJcity Intro)'), RequestStatus::Declined, 1, $at(90));
        $this->request($list, ['custom_artist' => 'Beyoncé', 'custom_title' => 'Crazy In Love'], RequestStatus::Accepted, 4, $at(110));
        $this->request($list, ['custom_artist' => 'Black Eyed Peas', 'custom_title' => 'I Gotta Feeling'], RequestStatus::Pending, 1, $at(200));

        return $gig;
    }

    /**
     * Seven weeks ago, private, with requests made with a guest pass. Its imported session is left
     * unlinked, so it's suggested as the gig's history.
     */
    protected function seedEngagementGig(Library $main): Gig
    {
        $gig = Gig::query()->create([
            'name' => 'Chloe & Dan\'s Engagement Party',
            'venue' => 'The Orangery',
            'location' => 'Bath',
            'starts_at' => $this->localTime(49, '19:00')->utc(),
            'ends_at' => $this->localTime(49, '23:30')->utc(),
            'timezone' => $this->timezone,
            'notes' => 'Guests request with the pass on the invitation.',
            'request_settings' => new GigRequestSettings(show_public_list: false, show_pass_labels: true),
        ]);

        $list = $this->songLists->requestsListFor($gig);
        $guests = $this->guestPass($list, 'Guests', lastUsedAt: $gig->ends_at);
        $this->guestPass($list, 'First invite link', revokedAt: $gig->starts_at->subWeek());

        $at = fn (int $minutes) => $gig->starts_at->addMinutes($minutes);

        $this->request($list, $this->songOf($main, 'Romeo & Juliet'), RequestStatus::Played, 5, $at(40), ['guest_pass_id' => $guests->id, 'requester_name' => 'Chloe', 'show_requester_name' => true, 'message' => 'Our song!'], $guests);
        $this->request($list, $this->songOf($main, 'Diamonds (keyløud Remix)'), RequestStatus::Played, 2, $at(75), ['guest_pass_id' => $guests->id], $guests);
        $this->request($list, ['custom_artist' => 'Whitney Houston', 'custom_title' => 'I Wanna Dance with Somebody'], RequestStatus::Accepted, 3, $at(120), ['guest_pass_id' => $guests->id, 'requester_name' => 'Auntie Jo'], $guests);

        return $gig;
    }

    /**
     * On now and pinned, with requests open, the tracklist on and a live session from the CLI tool,
     * so now playing is set. Requests go through the real requester, so matching, upvotes and the
     * do not play check run as they do for visitors.
     */
    protected function seedLiveGig(Library $main): Gig
    {
        $startsAt = CarbonImmutable::now($this->timezone)->subMinutes(90)->floorUnit('minute', 15);

        $gig = Gig::query()->create([
            'name' => 'Friday Late Session',
            'venue' => 'The Basement',
            'location' => 'Manchester',
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addHours(5)->utc(),
            'timezone' => $this->timezone,
            'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Open, max_requests_per_visitor: 5, show_tracklist: true),
        ]);
        $this->currentGig->pin($gig);

        $setlist = SongList::query()->create(['gig_id' => $gig->id, 'kind' => SongListKind::Setlist, 'name' => 'Friday Late Session', 'visibility' => SongListVisibility::Public]);
        $this->setlistSongs($setlist, [
            [$this->songOf($main, 'Water'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Calm Down (DJ Afron Remix)'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'YIPPEE-KI-YAY (DJcity Intro - Dirty)'), SetlistSongType::DoNotPlay, 'Not this crowd'],
        ]);

        $requests = [
            [$this->songOf($main, 'Water'), 'Mia', 'Birthday girl here!', 4],
            [['custom_artist' => 'tems', 'custom_title' => 'love me jeje'], null, null, 2],
            [$this->songOf($main, 'Not Like Us (DJcity Intro - Dirty)'), 'Jordan', null, 7],
            [$this->songOf($main, 'Espresso (keyløud Afro House Remix)'), null, null, 3],
            [['custom_artist' => 'Uncle Waffles', 'custom_title' => 'Tanzania'], null, 'Any amapiano please', 1],
            [['custom_artist' => 'Beyoncé', 'custom_title' => 'Crazy In Love'], 'Sam', null, 2],
            [$this->songOf($main, 'YIPPEE-KI-YAY (DJcity Intro - Dirty)'), null, null, 1],
            [$this->songOf($main, 'Pink Pony Club (DJcity Intro)'), null, null, 1],
        ];

        $items = [];

        foreach ($requests as $index => [$song, $name, $message, $votes]) {
            $items[] = $item = $this->requester->submit($gig, new SongRequestData(
                $song['canonical_track_id'] ?? null,
                $song['custom_artist'] ?? null,
                $song['custom_title'] ?? null,
                $name,
                $message,
                $name !== null,
                $this->visitor("live-{$index}"),
            ))['item'];

            for ($voter = 1; $voter < $votes; $voter++) {
                $this->votes->add($item, $this->visitor("live-{$index}-{$voter}"));
            }

            $item->forceFill(['created_at' => $startsAt->addMinutes(5 + $index * 8)])->saveQuietly();
        }

        $items[3]->update(['status' => RequestStatus::Accepted]);
        $items[6]->update(['status' => RequestStatus::Declined]);
        $items[7]->update(['status' => RequestStatus::Declined]);

        // A play every five minutes, up to a few minutes ago. Water and Love Me JeJe were requested, so they're
        // marked played; one play is only reported by name, and one isn't in the library. Each
        // report is a request of its own in production, so the gig is loaded afresh, with its
        // requests list.
        $gig->refresh();
        $tracks = $main->tracks()->with('artist')->whereNotIn('title', ['Water', 'Love Me JeJe', 'YIPPEE-KI-YAY (DJcity Intro - Dirty)'])->inRandomOrder(fake()->numberBetween())->limit(14)->get()->all();
        array_splice($tracks, 8, 0, [$main->tracks()->with('artist')->where('title', 'Water')->firstOrFail()]);
        array_splice($tracks, 12, 0, [$main->tracks()->with('artist')->where('title', 'Love Me JeJe')->firstOrFail()]);

        foreach (array_values($tracks) as $index => $track) {
            $playedAt = CarbonImmutable::now()->subMinutes(3 + (count($tracks) - 1 - $index) * 5);

            $this->nowPlaying->record($gig, match ($index) {
                5 => new NowPlayingData(null, null, $track->artist?->name, $track->title, $playedAt),
                10 => new NowPlayingData(null, null, 'Unknown Artist', 'ID - ID (White Label)', $playedAt),
                default => new NowPlayingData($track->source_track_id, $main->id, $track->artist?->name, $track->title, $playedAt),
            });
        }

        return $gig;
    }

    /**
     * In three weeks, with requests opening two hours before and the couple's setlist shared by
     * guest pass
     */
    protected function seedWeddingGig(Library $main): Gig
    {
        $startsAt = $this->localTime(-21, '18:00');

        $gig = Gig::query()->create([
            'name' => 'Sam & Alex\'s Wedding',
            'venue' => 'Hawthorn Barn',
            'location' => 'Cotswolds',
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addHours(6)->utc(),
            'timezone' => $this->timezone,
            'notes' => 'First dance at 20:00. No Macarena!',
            'request_settings' => new GigRequestSettings(requests_mode: RequestsMode::Scheduled, requests_open_minutes_before: 120, max_requests_per_visitor: 2),
        ]);

        $setlist = SongList::query()->create([
            'gig_id' => $gig->id,
            'kind' => SongListKind::Setlist,
            'name' => 'Sam & Alex\'s list',
            'description' => 'Songs the couple want, might like, and really don\'t want.',
            'visibility' => SongListVisibility::Private,
        ]);

        $couple = $this->guestPass($setlist, 'Couple', lastUsedAt: CarbonImmutable::now()->subDays(2));
        $this->guestPass($setlist, 'Planner', canAdd: false);
        $this->guestPass($setlist, 'Best man', expiresAt: CarbonImmutable::now()->subDays(5));
        $this->guestPass($setlist, 'Old link', revokedAt: CarbonImmutable::now()->subWeeks(2));

        $this->setlistSongs($setlist, [
            [['custom_artist' => 'Ed Sheeran', 'custom_title' => 'Perfect'], SetlistSongType::MustPlay, 'First dance'],
            [$this->songOf($main, 'Romeo & Juliet'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Where Are Ü Now (Maesic & Diplo Remix)'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Show Me Love'), SetlistSongType::Maybe, null],
            [['custom_artist' => 'ABBA', 'custom_title' => 'Dancing Queen'], SetlistSongType::Maybe, 'If the parents are up'],
            [['custom_artist' => 'Los del Río', 'custom_title' => 'Macarena'], SetlistSongType::DoNotPlay, null],
            [$this->songOf($main, 'Bitch Better Have My Money'), SetlistSongType::DoNotPlay, null],
        ], $couple, 'Sam');

        return $gig;
    }

    /**
     * Gigs made from history, as the DJ would from the history page
     */
    protected function seedHistoryGigs(): void
    {
        $this->linker->createGig($this->sessions('warehouse'), 'Warehouse Night');
        $this->linker->createGig($this->sessions('garden'), 'Garden Party');
    }

    /**
     * Setlists without a gig: a public one to share, and a private one to copy from
     */
    protected function seedSetlists(Library $main): void
    {
        $warmUp = SongList::query()->create(['kind' => SongListKind::Setlist, 'name' => 'Afro House Warm Up', 'description' => 'Easing into the night.', 'visibility' => SongListVisibility::Public]);
        $this->setlistSongs($warmUp, [
            [$this->songOf($main, 'People (SEVENTH Remix)'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Me & U (SEVENTH Remix)'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Feelings (David Mackay Remix)'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Lost (Adeema Remix)'), SetlistSongType::Maybe, null],
            [$this->songOf($main, 'Hide N Seek (Vidojean X Oliver Loenn Remix)'), SetlistSongType::Maybe, 'Only if it\'s quiet'],
        ]);

        $classics = SongList::query()->create(['kind' => SongListKind::Setlist, 'name' => 'Wedding Classics', 'visibility' => SongListVisibility::Private]);
        $this->setlistSongs($classics, [
            [['custom_artist' => 'Whitney Houston', 'custom_title' => 'I Wanna Dance with Somebody'], SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Temperature (TEDDY-O Remix)'), SetlistSongType::MustPlay, null],
            [$this->songOf($main, 'Whenever, Wherever (JARP & Dario Hessabi Remix)'), SetlistSongType::Maybe, null],
            [['custom_artist' => 'Black Lace', 'custom_title' => 'Agadoo'], SetlistSongType::DoNotPlay, null],
        ]);
    }

    /**
     * A request on a list, made at the given time, with votes from other visitors
     *
     * @param  array<string, mixed>  $song  The canonical track ID, or a custom artist and title
     * @param  array<string, mixed>  $attributes
     */
    protected function request(SongList $list, array $song, RequestStatus $status, int $votes, CarbonImmutable $at, array $attributes = [], ?GuestPass $pass = null): void
    {
        $visitor = $this->visitor("{$list->id}-{$at->timestamp}");

        $item = $this->songLists->add($list, [
            ...$song,
            ...$attributes,
            'status' => $status,
            'visitor_hash' => $visitor,
            'played_at' => $status === RequestStatus::Played ? $at->addMinutes(20) : null,
        ]);
        $item->forceFill(['created_at' => $at])->saveQuietly();

        for ($voter = 0; $voter < $votes; $voter++) {
            $this->votes->add($item, $voter === 0 ? $visitor : $this->visitor("{$list->id}-{$at->timestamp}-{$voter}"), $pass);
        }
    }

    /**
     * @param  list<array{0: array<string, mixed>, 1: SetlistSongType, 2: string|null}>  $songs
     */
    protected function setlistSongs(SongList $list, array $songs, ?GuestPass $pass = null, ?string $guestName = null): void
    {
        foreach ($songs as $index => [$song, $type, $note]) {
            // The guest adds the songs they typed in; the DJ adds the rest
            $byGuest = $pass !== null && isset($song['custom_title']);

            $this->songLists->add($list, [
                ...$song,
                'type' => $type,
                'note' => $note,
                'guest_pass_id' => $byGuest ? $pass->id : null,
                'guest_name' => $byGuest ? $guestName : null,
            ], $byGuest ? null : $this->admin);
        }
    }

    protected function guestPass(
        SongList $list,
        string $label,
        bool $canAdd = true,
        ?CarbonImmutable $expiresAt = null,
        ?CarbonImmutable $revokedAt = null,
        ?CarbonImmutable $lastUsedAt = null,
    ): GuestPass {
        return $list->guestPasses()->create([
            'label' => $label,
            'password' => self::GUEST_PASSWORD,
            'can_add' => $canAdd,
            'expires_at' => $expiresAt,
            'revoked_at' => $revokedAt,
            'last_used_at' => $lastUsedAt,
        ]);
    }

    /**
     * The song a library track (by its title) is matched to
     */
    protected function song(Library $library, string $title): CanonicalTrack
    {
        return CanonicalTrack::query()
            ->whereHas('libraryTracks', fn ($query) => $query->where('library_id', $library->id)->where('title', $title))
            ->firstOrFail();
    }

    /**
     * @return array{canonical_track_id: int}
     */
    protected function songOf(Library $library, string $title): array
    {
        return ['canonical_track_id' => $this->song($library, $title)->id];
    }

    /**
     * The imported sessions of one of the SETS
     *
     * @return Collection<int, HistorySession>
     */
    protected function sessions(string $set): Collection
    {
        return HistorySession::query()->where('source_history_id', 'like', "demo-{$set}-%")->get();
    }

    /**
     * A time of day, a number of days ago (or ahead, when negative), in the DJ's timezone
     */
    protected function localTime(int $daysAgo, string $time): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone)->subDays($daysAgo)->setTimeFromTimeString($time);
    }

    /**
     * A made-up visitor, hashed like a real one
     */
    protected function visitor(string $seed): string
    {
        return hash('sha256', "demo-visitor-{$seed}");
    }
}

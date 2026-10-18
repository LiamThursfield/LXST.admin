<?php

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\MergeAction;
use App\Enums\Dj\TrackService;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use App\Services\Dj\Import\Data\ArtistData;
use App\Services\Dj\Import\Data\TrackData;
use App\Services\Dj\Import\ImportSource;
use App\Services\Dj\Import\LibraryImporter;
use App\Services\Dj\Matching\CanonicalMerger;
use App\Services\Dj\Matching\CanonicalRematcher;
use App\Services\Dj\Matching\MatchSuggestions;
use App\Services\Dj\Matching\TrackNormaliser;

/**
 * Imports the given tracks into a new library through the real importer
 *
 * @param  list<TrackData>  $tracks
 * @param  list<ArtistData>  $artists
 */
function importTracks(array $tracks, array $artists = []): Library
{
    $library = Library::factory()->create();
    $source = new class($tracks, $artists) implements ImportSource
    {
        /**
         * @param  list<TrackData>  $tracks
         * @param  list<ArtistData>  $artists
         */
        public function __construct(private array $tracks, private array $artists) {}

        public function trackCount(): int
        {
            return count($this->tracks);
        }

        public function artists(): iterable
        {
            return $this->artists;
        }

        public function tags(): iterable
        {
            return [];
        }

        public function trackChunks(int $size): iterable
        {
            return [$this->tracks];
        }

        public function historySessions(): iterable
        {
            return [];
        }
    };

    app(LibraryImporter::class)->import($library, $source, 500);

    return $library;
}

/**
 * A canonical track with its match key, as imports create them
 *
 * @param  array<string, mixed>  $attributes
 */
function canonicalTrack(array $attributes): CanonicalTrack
{
    $track = CanonicalTrack::factory()->create($attributes);
    $track->update(['match_key' => app(TrackNormaliser::class)->titleKey($track->title)]);

    return $track;
}

test('imports link DJ versions of a song to one canonical track, named by the song', function () {
    $library = importTracks([
        new TrackData('1', 'Humble (DJcity Intro - Dirty)', sourceArtistId: 'a'),
        new TrackData('2', 'HUMBLE.', sourceArtistId: 'a'),
        new TrackData('3', 'Humble (Skrillex Remix)', sourceArtistId: 'a'),
    ], [new ArtistData('a', 'Kendrick Lamar')]);

    $canonicalIds = $library->tracks()->orderBy('source_track_id')->pluck('canonical_track_id');

    expect($canonicalIds[0])->toBe($canonicalIds[1])
        ->and($canonicalIds[2])->not->toBe($canonicalIds[0])
        ->and(CanonicalTrack::query()->find($canonicalIds[0])->title)->toBe('Humble')
        ->and(CanonicalTrack::query()->find($canonicalIds[2])->title)->toBe('Humble (Skrillex Remix)');
});

test('imports link artists credited with a featured artist to the main artist', function () {
    importTracks([], [new ArtistData('a', 'Tion Wayne'), new ArtistData('b', 'Tion Wayne ft. Aitch')]);

    expect(CanonicalArtist::count())->toBe(1)
        ->and(LibraryArtist::query()->pluck('canonical_artist_id')->unique())->toHaveCount(1);
});

test('imports link the same streaming track whatever its title', function () {
    importTracks([new TrackData('1', 'Glue', location: 'tidal:tracks:123')]);
    $other = importTracks([new TrackData('9', 'Glue (Bicep Edit)', location: 'tidal:tracks:123')]);

    expect(CanonicalTrack::count())->toBe(1)
        ->and($other->tracks()->sole()->service)->toBe(TrackService::Tidal);
});

test('the re-match merges duplicates, links unmatched tracks and names tracks by their song', function () {
    $kendrick = CanonicalArtist::factory()->create(['name' => 'Kendrick Lamar']);
    $kendrickFeat = CanonicalArtist::factory()->create(['name' => 'Kendrick Lamar ft. Lefty Gunplay']);
    $dirty = canonicalTrack(['title' => 'Humble (Dirty)', 'canonical_artist_id' => $kendrick->id]);
    $intro = canonicalTrack(['title' => 'Humble (DJcity Intro)', 'canonical_artist_id' => $kendrickFeat->id]);
    $remix = canonicalTrack(['title' => 'Humble (Skrillex Remix)', 'canonical_artist_id' => $kendrick->id]);
    $first = LibraryTrack::factory()->create(['canonical_track_id' => $dirty->id]);
    $second = LibraryTrack::factory()->create(['canonical_track_id' => $intro->id]);
    $unmatched = LibraryTrack::factory()->create(['title' => 'Untitled demo', 'canonical_track_id' => null]);

    $counts = app(CanonicalRematcher::class)->run();

    expect($counts)->toMatchArray(['artists_merged' => 1, 'tracks_merged' => 1, 'tracks_linked' => 1])
        ->and($first->fresh()->canonical_track_id)->toBe($second->fresh()->canonical_track_id)
        ->and($first->fresh()->canonicalTrack->title)->toBe('Humble')
        ->and($remix->fresh()->title)->toBe('Humble (Skrillex Remix)')
        ->and($unmatched->fresh()->canonical_track_id)->not->toBeNull()
        ->and(CanonicalMerge::query()->where('record', CanonicalRecord::Artist)->sole()->user_id)->toBeNull()
        ->and(CanonicalMerge::query()->where('record', CanonicalRecord::Track)->count())->toBe(1);
});

test('the re-match never merges a verified record away', function () {
    $artist = CanonicalArtist::factory()->create();
    $verified = canonicalTrack(['title' => 'Glue (Dirty)', 'canonical_artist_id' => $artist->id, 'verified_at' => now()]);
    $alsoVerified = canonicalTrack(['title' => 'Glue (Clean)', 'canonical_artist_id' => $artist->id, 'verified_at' => now()]);
    $unverified = canonicalTrack(['title' => 'Glue', 'canonical_artist_id' => $artist->id]);

    app(CanonicalRematcher::class)->run();

    expect($verified->fresh())->not->toBeNull()
        ->and($alsoVerified->fresh())->not->toBeNull()
        ->and($unverified->fresh())->toBeNull()
        // Verified titles are left as they are
        ->and($verified->fresh()->title)->toBe('Glue (Dirty)');
});

test('the re-match merges canonical tracks that share a streaming track', function () {
    $first = canonicalTrack(['title' => 'Glue']);
    $second = canonicalTrack(['title' => 'Glue (Bicep Edit)']);
    LibraryTrack::factory()->create(['canonical_track_id' => $first->id, 'service' => TrackService::Tidal, 'service_track_id' => '123']);
    LibraryTrack::factory()->create(['canonical_track_id' => $second->id, 'service' => TrackService::Tidal, 'service_track_id' => '123']);

    app(CanonicalRematcher::class)->run();

    expect(LibraryTrack::query()->pluck('canonical_track_id')->unique())->toHaveCount(1);
});

test('near matches are suggested, but numbered series and dismissed pairs are not', function () {
    $timberlake = CanonicalArtist::factory()->create(['name' => 'Justin Timberlake', 'match_key' => 'justin timberlake']);
    $typo = CanonicalArtist::factory()->create(['name' => 'Juston Timberlake', 'match_key' => 'juston timberlake']);
    CanonicalArtist::factory()->create(['name' => 'AFRO HOUSE 2024', 'match_key' => 'afro house 2024']);
    CanonicalArtist::factory()->create(['name' => 'AFRO HOUSE 2026', 'match_key' => 'afro house 2026']);
    $artist = CanonicalArtist::factory()->create();
    canonicalTrack(['title' => 'Gunshots 1', 'canonical_artist_id' => $artist->id]);
    canonicalTrack(['title' => 'Gunshots 2', 'canonical_artist_id' => $artist->id]);
    canonicalTrack(['title' => 'Mirrors', 'canonical_artist_id' => $timberlake->id]);
    canonicalTrack(['title' => 'Mirors', 'canonical_artist_id' => $timberlake->id]);

    $suggestions = app(MatchSuggestions::class);

    expect($suggestions->artists()->map(fn ($pair) => [$pair['first']->name, $pair['second']->name])->all())
        ->toBe([['Justin Timberlake', 'Juston Timberlake']])
        ->and($suggestions->tracks()->map(fn ($pair) => [$pair['first']->title, $pair['second']->title])->all())
        ->toBe([['Mirrors', 'Mirors']]);

    app(CanonicalMerger::class)->dismiss(CanonicalRecord::Artist, $typo->id, $timberlake->id);

    expect($suggestions->artists())->toBeEmpty();
});

test('merging moves library records and history to the kept record, and logs it', function () {
    $user = $this->createUserWithPermissions([]);
    $kept = canonicalTrack(['title' => 'Glue']);
    $merged = canonicalTrack(['title' => 'Glu']);
    $track = LibraryTrack::factory()->create(['canonical_track_id' => $merged->id]);
    HistoryPlay::factory()->create(['library_track_id' => $track->id]);

    $merge = app(CanonicalMerger::class)->mergeTracks($kept, $merged, $user);

    expect($track->fresh()->canonical_track_id)->toBe($kept->id)
        ->and($kept->libraryTracks()->sole()->historyPlays()->count())->toBe(1)
        ->and($merged->fresh())->toBeNull()
        ->and($merge->action)->toBe(MergeAction::Merge)
        ->and($merge->source_name)->toBe('Glu')
        ->and($merge->target_name)->toBe('Glue')
        ->and($merge->moved_count)->toBe(1)
        ->and($merge->user_id)->toBe($user->id);
});

test('splitting gives a library track its own verified canonical track', function () {
    $canonical = canonicalTrack(['title' => 'Glue']);
    $wrong = LibraryTrack::factory()->create(['title' => 'Glue (Different Song)', 'canonical_track_id' => $canonical->id]);
    LibraryTrack::factory()->create(['canonical_track_id' => $canonical->id]);

    $split = app(CanonicalMerger::class)->splitTrack($wrong);

    expect($wrong->fresh()->canonical_track_id)->toBe($split->id)
        ->and($split->isVerified())->toBeTrue()
        ->and($split->title)->toBe('Glue (Different Song)')
        ->and($canonical->fresh())->not->toBeNull()
        ->and(CanonicalMerge::query()->sole()->action)->toBe(MergeAction::Split);

    // Matching won't put it back
    app(CanonicalRematcher::class)->run();

    expect($wrong->fresh()->canonical_track_id)->toBe($split->id);
});

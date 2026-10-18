<?php

namespace App\Services\Dj\Import;

use App\Enums\Dj\TrackService;
use App\Models\Dj\HistoryPlay;
use App\Models\Dj\HistorySession;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTag;
use App\Models\Dj\LibraryTrack;
use App\Services\Dj\Import\Data\TrackData;
use App\Services\Dj\Matching\CanonicalMatcher;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Imports a full snapshot of a library from any source. Records are created or updated by their
 * source ID, so re-imports never duplicate, and linked to canonical records by the automatic
 * matching rules (see CanonicalMatcher). Tracks the snapshot leaves out are marked missing rather
 * than deleted, as history points at them.
 */
class LibraryImporter
{
    public function __construct(protected CanonicalMatcher $matcher) {}

    /** @var array<string, int> */
    protected array $counts = [];

    /**
     * @param  (Closure(int $processed): void)|null  $onProgress  Called after each chunk of tracks
     * @return array<string, int> What changed, e.g. tracks_added
     */
    public function import(Library $library, ImportSource $source, int $chunkSize, ?Closure $onProgress = null): array
    {
        $this->counts = array_fill_keys([
            'artists_added', 'artists_updated', 'tags',
            'tracks_added', 'tracks_updated', 'tracks_unchanged', 'tracks_skipped', 'tracks_missing',
            'history_sessions', 'history_plays',
        ], 0);

        $artists = $this->importArtists($library, $source);
        $tags = $this->importTags($library, $source);

        $seen = [];
        $processed = 0;

        foreach ($source->trackChunks($chunkSize) as $chunk) {
            DB::transaction(function () use ($library, $chunk, $artists, $tags, &$seen) {
                $this->importTracks($library, $chunk, $artists, $tags, $seen);
            });

            $processed += count($chunk);

            if ($onProgress !== null) {
                $onProgress($processed);
            }
        }

        $this->markMissing($library, $seen);
        $this->importHistory($library, $source);

        return $this->counts;
    }

    /**
     * @return array<string, LibraryArtist> Keyed by source artist ID
     */
    protected function importArtists(Library $library, ImportSource $source): array
    {
        $existing = $library->artists()->get()->keyBy('source_artist_id')->all();

        foreach ($source->artists() as $data) {
            $artist = $existing[$data->sourceArtistId] ?? new LibraryArtist([
                'library_id' => $library->id,
                'source_artist_id' => $data->sourceArtistId,
            ]);

            $isNew = ! $artist->exists;
            $artist->name = $data->name;
            $artist->canonical_artist_id ??= $this->matcher->artistFor($data->name)->id;

            if ($artist->isDirty()) {
                $artist->save();
                $this->counts[$isNew ? 'artists_added' : 'artists_updated']++;
            }

            $existing[$data->sourceArtistId] = $artist;
        }

        return $existing;
    }

    /**
     * @return array<string, int> Library tag IDs, keyed by source tag ID
     */
    protected function importTags(Library $library, ImportSource $source): array
    {
        $ids = [];

        foreach ($source->tags() as $data) {
            $tag = LibraryTag::query()->updateOrCreate(
                ['library_id' => $library->id, 'source_tag_id' => $data->sourceTagId],
                ['name' => $data->name, 'category' => $data->category],
            );

            $ids[$data->sourceTagId] = $tag->id;
            $this->counts['tags']++;
        }

        return $ids;
    }

    /**
     * @param  list<TrackData>  $chunk
     * @param  array<string, LibraryArtist>  $artists
     * @param  array<string, int>  $tags
     * @param  array<string, true>  $seen
     */
    protected function importTracks(Library $library, array $chunk, array $artists, array $tags, array &$seen): void
    {
        $existing = $library->tracks()
            ->whereIn('source_track_id', array_map(fn (TrackData $data) => $data->sourceTrackId, $chunk))
            ->get()
            ->keyBy('source_track_id');

        foreach ($chunk as $data) {
            if ($data->title === '') {
                $this->counts['tracks_skipped']++;

                continue;
            }

            $seen[$data->sourceTrackId] = true;

            /** @var LibraryTrack $track */
            $track = $existing->get($data->sourceTrackId) ?? new LibraryTrack([
                'library_id' => $library->id,
                'source_track_id' => $data->sourceTrackId,
            ]);

            $isNew = ! $track->exists;
            $artist = $data->sourceArtistId !== null ? ($artists[$data->sourceArtistId] ?? null) : null;
            $service = TrackService::parseLocation($data->location);

            $track->fill([
                'library_artist_id' => $artist?->id,
                'title' => $data->title,
                'album' => $data->album,
                'genre' => $data->genre,
                'label' => $data->label,
                'year' => $data->year,
                'bpm' => $data->bpm,
                'duration' => $data->duration,
                'key' => $data->key,
                'comments' => $data->comments,
                'play_count' => $data->playCount,
                'rating' => $data->rating,
                'date_added' => $data->dateAdded,
                'location' => $data->location,
                'service' => $service[0] ?? null,
                'service_track_id' => $service[1] ?? null,
                'missing_since' => null,
            ]);

            $track->canonical_track_id ??= $this->matcher->trackFor(
                $data->title,
                $artist?->canonical_artist_id,
                $service[0] ?? null,
                $service[1] ?? null,
                $data->duration,
                $data->bpm,
                $data->key,
            )->id;

            $changed = $track->isDirty();

            if ($changed) {
                $track->save();
            }

            $tagIds = array_values(array_filter(array_map(fn (string $id) => $tags[$id] ?? null, $data->sourceTagIds)));
            $tagChanges = $track->tags()->sync($tagIds);
            $changed = $changed || array_filter($tagChanges) !== [];

            $this->counts[match (true) {
                $isNew => 'tracks_added',
                $changed => 'tracks_updated',
                default => 'tracks_unchanged',
            }]++;
        }
    }

    /**
     * Flags the library's tracks this snapshot didn't include
     *
     * @param  array<string, true>  $seen
     */
    protected function markMissing(Library $library, array $seen): void
    {
        $missingIds = $library->tracks()
            ->present()
            ->pluck('source_track_id', 'id')
            ->reject(fn (string $sourceTrackId) => isset($seen[$sourceTrackId]))
            ->keys();

        foreach ($missingIds->chunk(1000) as $ids) {
            LibraryTrack::query()->whereKey($ids->all())->update(['missing_since' => CarbonImmutable::now()]);
        }

        $this->counts['tracks_missing'] = $missingIds->count();
    }

    /**
     * Keeps each session as imported, replacing its plays on a re-import. Empty sessions are left out.
     */
    protected function importHistory(Library $library, ImportSource $source): void
    {
        $trackIds = $library->tracks()->pluck('id', 'source_track_id');

        foreach ($source->historySessions() as $data) {
            if ($data->plays === []) {
                continue;
            }

            $playedAt = array_filter(array_column($data->plays, 'played_at'));

            DB::transaction(function () use ($library, $data, $playedAt, $trackIds) {
                $session = HistorySession::query()->firstOrNew(
                    ['library_id' => $library->id, 'source_history_id' => $data->sourceHistoryId],
                );

                // A re-import keeps any gig link and stats choice made since
                if (! $session->exists) {
                    $session->include_in_stats = count($data->plays) >= (int) config('dj.history.min_plays_for_stats');
                }

                $session->fill([
                    'name' => $data->name,
                    'started_at' => $playedAt === [] ? null : min($playedAt),
                    'ended_at' => $playedAt === [] ? null : max($playedAt),
                ])->save();

                $session->plays()->delete();

                $now = CarbonImmutable::now();

                HistoryPlay::query()->insert(array_map(fn (array $play) => [
                    'history_session_id' => $session->id,
                    'library_track_id' => $trackIds[$play['source_track_id']] ?? null,
                    'source_track_id' => $play['source_track_id'],
                    'position' => $play['position'],
                    'played_at' => $play['played_at'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $data->plays));
            });

            $this->counts['history_sessions']++;
            $this->counts['history_plays'] += count($data->plays);
        }
    }
}

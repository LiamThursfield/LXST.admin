<?php

namespace App\Services\Dj\Matching;

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\MergeAction;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Points library tracks or artists at a canonical record chosen by hand. The record is verified,
 * so a re-match never moves them back, and records left with nothing linked are deleted unless
 * they're verified. Each record they came from is logged.
 */
class CanonicalAssigner
{
    public function __construct(protected TrackNormaliser $normaliser) {}

    /**
     * Each track keeps its own library artist, whatever the song's artist is
     *
     * @param  Collection<int, LibraryTrack>  $tracks
     */
    public function assignTracks(Collection $tracks, CanonicalTrack $song, ?User $user = null): CanonicalTrack
    {
        return DB::transaction(function () use ($tracks, $song, $user): CanonicalTrack {
            $previous = $tracks->groupBy(fn (LibraryTrack $track) => (int) $track->canonical_track_id);

            LibraryTrack::query()->whereKey($tracks->modelKeys())->update(['canonical_track_id' => $song->id]);
            $song->update(['verified_at' => $song->verified_at ?? now()]);

            foreach ($previous as $previousId => $moved) {
                if ($previousId === $song->id) {
                    continue;
                }

                $old = $previousId === 0 ? null : CanonicalTrack::query()->find($previousId);

                $this->log(CanonicalRecord::Track, $song->id, $song->title, $old?->id, $old !== null ? $old->title : (string) ($moved->first()->title ?? ''), $moved->count(), $user);

                if ($old !== null && ! $old->isVerified() && ! $old->libraryTracks()->exists()) {
                    $old->delete();
                }
            }

            return $song;
        });
    }

    /**
     * A new song, named as given (or after the first track), by the first track's artist
     *
     * @param  Collection<int, LibraryTrack>  $tracks
     */
    public function assignTracksToNewSong(Collection $tracks, ?string $title, ?User $user = null): CanonicalTrack
    {
        /** @var LibraryTrack $first */
        $first = $tracks->first();
        $title = trim((string) $title) !== '' ? trim((string) $title) : $this->normaliser->baseTitle($first->title);

        $song = CanonicalTrack::query()->create([
            'canonical_artist_id' => $first->artist?->canonical_artist_id,
            'title' => $title,
            'match_key' => $this->normaliser->titleKey($title),
            'bpm' => $first->bpm,
            'duration' => $first->duration,
            'key' => $first->key,
            'verified_at' => now(),
        ]);

        return $this->assignTracks($tracks, $song, $user);
    }

    /**
     * Songs only these library artists' tracks are linked to move with them, so splitting two
     * artists who share a name splits their songs too
     *
     * @param  Collection<int, LibraryArtist>  $artists
     */
    public function assignArtists(Collection $artists, CanonicalArtist $target, ?User $user = null): CanonicalArtist
    {
        return DB::transaction(function () use ($artists, $target, $user): CanonicalArtist {
            $previous = $artists->groupBy(fn (LibraryArtist $artist) => (int) $artist->canonical_artist_id);
            $artistIds = $artists->modelKeys();

            LibraryArtist::query()->whereKey($artistIds)->update(['canonical_artist_id' => $target->id]);

            CanonicalTrack::query()
                ->whereHas('libraryTracks', fn ($tracks) => $tracks->whereIn('library_artist_id', $artistIds))
                ->whereDoesntHave('libraryTracks', fn ($tracks) => $tracks->where(fn ($tracks) => $tracks
                    ->whereNotIn('library_artist_id', $artistIds)
                    ->orWhereNull('library_artist_id')))
                ->update(['canonical_artist_id' => $target->id]);

            $target->update(['verified_at' => $target->verified_at ?? now()]);

            foreach ($previous as $previousId => $moved) {
                if ($previousId === $target->id) {
                    continue;
                }

                $old = $previousId === 0 ? null : CanonicalArtist::query()->find($previousId);

                $this->log(CanonicalRecord::Artist, $target->id, $target->name, $old?->id, $old !== null ? $old->name : (string) ($moved->first()->name ?? ''), $moved->count(), $user);

                if ($old !== null && ! $old->isVerified() && ! $old->libraryArtists()->exists() && ! $old->tracks()->exists()) {
                    $old->delete();
                }
            }

            return $target;
        });
    }

    /**
     * @param  Collection<int, LibraryArtist>  $artists
     */
    public function assignArtistsToNewArtist(Collection $artists, ?string $name, ?User $user = null): CanonicalArtist
    {
        /** @var LibraryArtist $first */
        $first = $artists->first();
        $name = trim((string) $name) !== '' ? trim((string) $name) : $first->name;

        $artist = CanonicalArtist::query()->create([
            'name' => $name,
            'match_key' => $this->normaliser->artistKey($name),
            'verified_at' => now(),
        ]);

        return $this->assignArtists($artists, $artist, $user);
    }

    protected function log(
        CanonicalRecord $record,
        int $targetId,
        string $targetName,
        ?int $sourceId,
        string $sourceName,
        int $moved,
        ?User $user,
    ): void {
        CanonicalMerge::query()->create([
            'record' => $record,
            'action' => MergeAction::Assign,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'source_id' => $sourceId,
            'source_name' => $sourceName,
            'moved_count' => $moved,
            'user_id' => $user?->id,
        ]);
    }
}

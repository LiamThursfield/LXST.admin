<?php

namespace App\Services\Dj\Matching;

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\MergeAction;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalMerge;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryTrack;
use App\Models\Dj\MatchDismissal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Merges and splits canonical records, moving their library records across and logging who did
 * it (no user means the re-match did it). History stays with the library tracks, so it follows.
 */
class CanonicalMerger
{
    public function __construct(protected TrackNormaliser $normaliser) {}

    /**
     * Moves the source artist's library artists and canonical tracks to the target, then deletes it
     */
    public function mergeArtists(CanonicalArtist $target, CanonicalArtist $source, ?User $user = null): CanonicalMerge
    {
        return DB::transaction(function () use ($target, $source, $user): CanonicalMerge {
            $moved = $source->libraryArtists()->update(['canonical_artist_id' => $target->id]);
            $source->tracks()->update(['canonical_artist_id' => $target->id]);

            $merge = $this->log(CanonicalRecord::Artist, MergeAction::Merge, $target->id, $target->name, $source->id, $source->name, $moved, $user);

            // A verified artist stays verified once merged
            if ($source->isVerified() && ! $target->isVerified()) {
                $target->update(['verified_at' => $source->verified_at]);
            }

            $source->delete();

            return $merge;
        });
    }

    /**
     * Moves the source track's library tracks to the target, then deletes it
     */
    public function mergeTracks(CanonicalTrack $target, CanonicalTrack $source, ?User $user = null): CanonicalMerge
    {
        return DB::transaction(function () use ($target, $source, $user): CanonicalMerge {
            $moved = $source->libraryTracks()->update(['canonical_track_id' => $target->id]);

            $merge = $this->log(CanonicalRecord::Track, MergeAction::Merge, $target->id, $target->title, $source->id, $source->title, $moved, $user);

            if ($source->isVerified() && ! $target->isVerified()) {
                $target->update(['verified_at' => $source->verified_at]);
            }

            $source->delete();

            return $merge;
        });
    }

    /**
     * Gives a library track a canonical track of its own, e.g. after a wrong match. The new record
     * is verified, so matching never puts it back.
     */
    public function splitTrack(LibraryTrack $track, ?User $user = null): CanonicalTrack
    {
        return DB::transaction(function () use ($track, $user): CanonicalTrack {
            $previous = $track->canonicalTrack;

            $canonical = CanonicalTrack::query()->create([
                'canonical_artist_id' => $previous !== null ? $previous->canonical_artist_id : $track->artist?->canonical_artist_id,
                'title' => $track->title,
                'match_key' => $this->normaliser->titleKey($track->title),
                'bpm' => $track->bpm,
                'duration' => $track->duration,
                'key' => $track->key,
                'verified_at' => now(),
            ]);

            $track->update(['canonical_track_id' => $canonical->id]);

            $this->log(CanonicalRecord::Track, MergeAction::Split, $canonical->id, $canonical->title, $previous?->id, $previous->title ?? $track->title, 1, $user);

            // A canonical track with nothing left in any library has nothing to count
            if ($previous !== null && ! $previous->libraryTracks()->exists() && ! $previous->isVerified()) {
                $previous->delete();
            }

            return $canonical;
        });
    }

    /**
     * Remembers that two records aren't the same, so they aren't suggested again
     */
    public function dismiss(CanonicalRecord $record, int $firstId, int $secondId, ?User $user = null): void
    {
        MatchDismissal::query()->firstOrCreate(
            ['record' => $record, 'first_id' => min($firstId, $secondId), 'second_id' => max($firstId, $secondId)],
            ['user_id' => $user?->id],
        );
    }

    protected function log(
        CanonicalRecord $record,
        MergeAction $action,
        ?int $targetId,
        string $targetName,
        ?int $sourceId,
        string $sourceName,
        int $moved,
        ?User $user,
    ): CanonicalMerge {
        return CanonicalMerge::query()->create([
            'record' => $record,
            'action' => $action,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'source_id' => $sourceId,
            'source_name' => $sourceName,
            'moved_count' => $moved,
            'user_id' => $user?->id,
        ]);
    }
}

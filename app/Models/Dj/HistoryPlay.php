<?php

namespace App\Models\Dj;

use Carbon\CarbonImmutable;
use Database\Factories\Dj\HistoryPlayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One track played in a history session
 *
 * @property int $id
 * @property int $history_session_id
 * @property int|null $library_track_id
 * @property int|null $canonical_track_id For plays added by hand, which name a song
 * @property string|null $source_track_id Kept in case the track arrives in a later import
 * @property int $position
 * @property CarbonImmutable|null $played_at (UTC)
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read HistorySession $session
 * @property-read LibraryTrack|null $track
 * @property-read CanonicalTrack|null $song
 */
class HistoryPlay extends Model
{
    /** @use HasFactory<HistoryPlayFactory> */
    use HasFactory;

    protected $table = 'dj_history_plays';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'history_session_id',
        'library_track_id',
        'canonical_track_id',
        'source_track_id',
        'position',
        'played_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'played_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<HistorySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(HistorySession::class, 'history_session_id');
    }

    /**
     * @return BelongsTo<LibraryTrack, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(LibraryTrack::class, 'library_track_id');
    }

    /**
     * The song added by hand
     *
     * @return BelongsTo<CanonicalTrack, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(CanonicalTrack::class, 'canonical_track_id');
    }

    /**
     * The song played: the one added by hand, or the library track's
     */
    public function playedSong(): ?CanonicalTrack
    {
        return $this->song ?? $this->track?->canonicalTrack;
    }
}

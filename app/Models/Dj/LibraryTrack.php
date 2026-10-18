<?php

namespace App\Models\Dj;

use App\Enums\Dj\MusicalKey;
use App\Enums\Dj\TrackService;
use App\Models\Dj\Concerns\HasDuration;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\LibraryTrackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A track as one library holds it, linked to its canonical track once matched
 *
 * @property int $id
 * @property int $library_id
 * @property int|null $library_artist_id
 * @property int|null $canonical_track_id
 * @property string $source_track_id The track's ID in the source, unique per library
 * @property string $title
 * @property string|null $album
 * @property string|null $genre
 * @property string|null $label
 * @property int|null $year
 * @property string|null $bpm
 * @property int|null $duration In seconds
 * @property MusicalKey|null $key
 * @property string|null $comments
 * @property int $play_count Rekordbox's own count, not plays recorded here
 * @property int|null $rating 0 to 5 stars
 * @property CarbonImmutable|null $date_added
 * @property string|null $location A file path, or a streaming ID such as tidal:tracks:356883832
 * @property TrackService|null $service
 * @property string|null $service_track_id
 * @property CarbonImmutable|null $missing_since When a full import stopped including the track
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Library $library
 * @property-read LibraryArtist|null $artist
 * @property-read CanonicalTrack|null $canonicalTrack
 */
class LibraryTrack extends Model
{
    /** @use HasFactory<LibraryTrackFactory> */
    use HasDuration, HasFactory;

    protected $table = 'dj_library_tracks';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'library_id',
        'library_artist_id',
        'canonical_track_id',
        'source_track_id',
        'title',
        'album',
        'genre',
        'label',
        'year',
        'bpm',
        'duration',
        'key',
        'comments',
        'play_count',
        'rating',
        'date_added',
        'location',
        'service',
        'service_track_id',
        'missing_since',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bpm' => 'decimal:2',
            'duration' => 'integer',
            'key' => MusicalKey::class,
            'year' => 'integer',
            'play_count' => 'integer',
            'rating' => 'integer',
            'date_added' => 'date',
            'service' => TrackService::class,
            'missing_since' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<LibraryArtist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(LibraryArtist::class, 'library_artist_id');
    }

    /**
     * @return BelongsTo<CanonicalTrack, $this>
     */
    public function canonicalTrack(): BelongsTo
    {
        return $this->belongsTo(CanonicalTrack::class);
    }

    /**
     * Plays of this track in the imported history, newest first
     *
     * @return HasMany<HistoryPlay, $this>
     */
    public function historyPlays(): HasMany
    {
        return $this->hasMany(HistoryPlay::class)->latest('played_at');
    }

    /**
     * @return BelongsToMany<LibraryTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(LibraryTag::class, 'dj_library_tag_track');
    }

    /**
     * Tracks the last full import still included
     *
     * @param  Builder<LibraryTrack>  $query
     */
    public function scopePresent(Builder $query): void
    {
        $query->whereNull('missing_since');
    }
}

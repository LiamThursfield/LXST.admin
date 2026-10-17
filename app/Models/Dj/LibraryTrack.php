<?php

namespace App\Models\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\Concerns\HasDuration;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\LibraryTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A track as one library holds it, linked to its canonical track once matched
 *
 * @property int $id
 * @property int $library_id
 * @property int|null $library_artist_id
 * @property int|null $canonical_track_id
 * @property string $source_track_id The track's ID in the source, unique per library
 * @property string $title
 * @property string|null $bpm
 * @property int|null $duration In seconds
 * @property MusicalKey|null $key
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
        'bpm',
        'duration',
        'key',
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
}

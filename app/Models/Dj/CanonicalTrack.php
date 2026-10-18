<?php

namespace App\Models\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\Concerns\HasDuration;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\CanonicalTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The real-world song behind library tracks, so the same song counts once across libraries
 * and re-imports
 *
 * @property int $id
 * @property int|null $canonical_artist_id
 * @property string $title
 * @property string|null $match_key The title, normalised for matching (see TrackNormaliser)
 * @property string|null $bpm
 * @property int|null $duration In seconds
 * @property MusicalKey|null $key
 * @property CarbonImmutable|null $verified_at When someone confirmed the details, so matching won't change them
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read CanonicalArtist|null $artist
 */
class CanonicalTrack extends Model
{
    /** @use HasFactory<CanonicalTrackFactory> */
    use HasDuration, HasFactory;

    protected $table = 'dj_canonical_tracks';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'canonical_artist_id',
        'title',
        'match_key',
        'bpm',
        'duration',
        'key',
        'verified_at',
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
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CanonicalArtist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(CanonicalArtist::class, 'canonical_artist_id');
    }

    /**
     * @return HasMany<LibraryTrack, $this>
     */
    public function libraryTracks(): HasMany
    {
        return $this->hasMany(LibraryTrack::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}

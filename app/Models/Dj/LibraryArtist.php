<?php

namespace App\Models\Dj;

use Carbon\CarbonImmutable;
use Database\Factories\Dj\LibraryArtistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An artist as one library names it, linked to its canonical artist once matched
 *
 * @property int $id
 * @property int $library_id
 * @property int|null $canonical_artist_id
 * @property string $source_artist_id The artist's ID in the source, unique per library
 * @property string $name
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Library $library
 * @property-read CanonicalArtist|null $canonicalArtist
 */
class LibraryArtist extends Model
{
    /** @use HasFactory<LibraryArtistFactory> */
    use HasFactory;

    protected $table = 'dj_library_artists';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'library_id',
        'canonical_artist_id',
        'source_artist_id',
        'name',
    ];

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<CanonicalArtist, $this>
     */
    public function canonicalArtist(): BelongsTo
    {
        return $this->belongsTo(CanonicalArtist::class);
    }

    /**
     * @return HasMany<LibraryTrack, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(LibraryTrack::class);
    }
}

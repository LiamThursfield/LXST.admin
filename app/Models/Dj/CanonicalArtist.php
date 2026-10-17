<?php

namespace App\Models\Dj;

use Carbon\CarbonImmutable;
use Database\Factories\Dj\CanonicalArtistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The real-world artist behind library artists, so the same artist counts once across
 * libraries and re-imports
 *
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $verified_at When someone confirmed the details, so matching won't change them
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class CanonicalArtist extends Model
{
    /** @use HasFactory<CanonicalArtistFactory> */
    use HasFactory;

    protected $table = 'dj_canonical_artists';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CanonicalTrack, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(CanonicalTrack::class);
    }

    /**
     * @return HasMany<LibraryArtist, $this>
     */
    public function libraryArtists(): HasMany
    {
        return $this->hasMany(LibraryArtist::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}

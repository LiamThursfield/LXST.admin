<?php

namespace App\Models\Dj;

use App\Enums\Dj\LibrarySource;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\LibraryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A DJ's music library, from one source (e.g. a Rekordbox export). The tenant is the DJ, so it
 * owns every library.
 *
 * @property int $id
 * @property string $name
 * @property LibrarySource $source
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Library extends Model
{
    /** @use HasFactory<LibraryFactory> */
    use HasFactory;

    protected $table = 'dj_libraries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LibrarySource::class,
        ];
    }

    /**
     * @return HasMany<LibraryArtist, $this>
     */
    public function artists(): HasMany
    {
        return $this->hasMany(LibraryArtist::class);
    }

    /**
     * @return HasMany<LibraryTrack, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(LibraryTrack::class);
    }
}

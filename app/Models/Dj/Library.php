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
 * @property CarbonImmutable|null $hidden_from_requests_at When it was hidden from song request search, with the songs only in it
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
        'hidden_from_requests_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LibrarySource::class,
            'hidden_from_requests_at' => 'datetime',
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

    /**
     * @return HasMany<LibraryTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(LibraryTag::class);
    }

    /**
     * @return HasMany<LibraryImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(LibraryImport::class);
    }

    /**
     * @return HasMany<HistorySession, $this>
     */
    public function historySessions(): HasMany
    {
        return $this->hasMany(HistorySession::class);
    }

    public function isHiddenFromRequests(): bool
    {
        return $this->hidden_from_requests_at !== null;
    }
}

<?php

namespace App\Models\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\Concerns\HasDuration;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\CanonicalTrackFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * @property CarbonImmutable|null $hidden_from_requests_at When it was hidden from song request search
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
        'hidden_from_requests_at',
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
            'hidden_from_requests_at' => 'datetime',
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

    public function isHiddenFromRequests(): bool
    {
        return $this->hidden_from_requests_at !== null;
    }

    /**
     * Whether it's hidden from requests, itself or with its artist
     */
    public function isHiddenFromRequestsWithArtist(): bool
    {
        return $this->isHiddenFromRequests() || ($this->artist?->isHiddenFromRequests() ?? false);
    }

    /**
     * Songs hidden from requests, themselves or with their artist (or, with false, the others)
     *
     * @param  Builder<CanonicalTrack>  $query
     */
    public function scopeHiddenFromRequests(Builder $query, bool $hidden = true): void
    {
        if ($hidden) {
            $query->where(fn (Builder $query) => $query
                ->whereNotNull('hidden_from_requests_at')
                ->orWhereHas('artist', fn (Builder $artist) => $artist->whereNotNull('hidden_from_requests_at')));

            return;
        }

        $query->whereNull('hidden_from_requests_at')
            ->whereDoesntHave('artist', fn (Builder $artist) => $artist->whereNotNull('hidden_from_requests_at'));
    }

    /**
     * Songs visitors can request: not hidden, themselves or with their artist, and still in a
     * library that isn't hidden, so orphans and songs the latest import dropped are left out
     *
     * @param  Builder<CanonicalTrack>  $query
     */
    public function scopeRequestable(Builder $query): void
    {
        $query->hiddenFromRequests(false)
            ->whereHas('libraryTracks', fn (Builder $tracks) => $tracks
                ->present()
                ->whereHas('library', fn (Builder $library) => $library->whereNull('hidden_from_requests_at')));
    }
}

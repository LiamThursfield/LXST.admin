<?php

namespace App\Models\Dj;

use Carbon\CarbonImmutable;
use Database\Factories\Dj\GuestPassFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A labelled, simple password for a song list, so people without a login (e.g. a bride or a
 * planner) can view and add to a setlist, or submit requests when a gig's requests need a pass.
 * It's a light block on open access, not a security feature.
 *
 * @property int $id
 * @property int $song_list_id
 * @property string $label e.g. "Bride" or "Planner"
 * @property string $password Encrypted, so the DJ can look it up and send it again
 * @property bool $can_add Whether it can add songs
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read SongList $songList
 */
class GuestPass extends Model
{
    /** @use HasFactory<GuestPassFactory> */
    use HasFactory;

    protected $table = 'dj_guest_passes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'song_list_id',
        'label',
        'password',
        'can_add',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'can_add' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SongList, $this>
     */
    public function songList(): BelongsTo
    {
        return $this->belongsTo(SongList::class);
    }

    /**
     * The songs it added
     *
     * @return HasMany<SongListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SongListItem::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Neither revoked nor expired
     */
    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * Whether the password matches, compared in constant time
     */
    public function matches(string $password): bool
    {
        return hash_equals(mb_strtolower(trim($this->password)), mb_strtolower(trim($password)));
    }

    /**
     * Passes that are neither revoked nor expired
     *
     * @param  Builder<GuestPass>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}

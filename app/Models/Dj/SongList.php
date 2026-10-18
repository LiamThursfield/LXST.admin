<?php

namespace App\Models\Dj;

use App\Enums\Dj\SongListKind;
use App\Enums\Dj\SongListVisibility;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\SongListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A list of songs: a setlist (e.g. a wedding's must plays), or a gig's requests (#120). A list can
 * belong to one gig, or none.
 *
 * @property int $id
 * @property int|null $gig_id
 * @property SongListKind $kind
 * @property string $name
 * @property string|null $description
 * @property SongListVisibility $visibility
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Gig|null $gig
 */
class SongList extends Model
{
    /** @use HasFactory<SongListFactory> */
    use HasFactory;

    protected $table = 'dj_song_lists';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'gig_id',
        'kind',
        'name',
        'description',
        'visibility',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SongListKind::class,
            'visibility' => SongListVisibility::class,
        ];
    }

    /**
     * @return BelongsTo<Gig, $this>
     */
    public function gig(): BelongsTo
    {
        return $this->belongsTo(Gig::class);
    }

    /**
     * @return HasMany<SongListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SongListItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Labelled passwords for people without a login
     *
     * @return HasMany<GuestPass, $this>
     */
    public function guestPasses(): HasMany
    {
        return $this->hasMany(GuestPass::class);
    }

    public function isSetlist(): bool
    {
        return $this->kind === SongListKind::Setlist;
    }

    /**
     * @param  Builder<SongList>  $query
     */
    public function scopeSetlists(Builder $query): void
    {
        $query->where('kind', SongListKind::Setlist);
    }
}

<?php

namespace App\Models\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\SongListItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A song on a list: one from the library (a canonical track), or a custom artist and title when
 * it isn't in it. Records who added it.
 *
 * @property int $id
 * @property int $song_list_id
 * @property int|null $canonical_track_id
 * @property string|null $custom_artist
 * @property string|null $custom_title
 * @property SetlistSongType|null $type Null on requests
 * @property int $position
 * @property string|null $note
 * @property int|null $added_by_user_id
 * @property int|null $guest_pass_id Comes with guest passes (#147)
 * @property string|null $visitor_hash
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read SongList $songList
 * @property-read CanonicalTrack|null $song
 * @property-read User|null $addedBy
 */
class SongListItem extends Model
{
    /** @use HasFactory<SongListItemFactory> */
    use HasFactory;

    protected $table = 'dj_song_list_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'song_list_id',
        'canonical_track_id',
        'custom_artist',
        'custom_title',
        'type',
        'position',
        'note',
        'added_by_user_id',
        'guest_pass_id',
        'visitor_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SetlistSongType::class,
            'position' => 'integer',
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
     * @return BelongsTo<CanonicalTrack, $this>
     */
    public function song(): BelongsTo
    {
        return $this->belongsTo(CanonicalTrack::class, 'canonical_track_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function title(): string
    {
        return $this->song !== null ? $this->song->title : (string) $this->custom_title;
    }

    public function artist(): ?string
    {
        return $this->song !== null ? $this->song->artist?->name : $this->custom_artist;
    }
}

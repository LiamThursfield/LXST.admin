<?php

namespace App\Models\Dj;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An upvote of a song request, one per visitor. The requester's own request counts as one.
 *
 * @property int $id
 * @property int $song_list_item_id
 * @property string|null $visitor_hash
 * @property int|null $guest_pass_id
 * @property CarbonImmutable|null $created_at
 * @property-read SongListItem $item
 */
class SongListItemVote extends Model
{
    public const ?string UPDATED_AT = null;

    protected $table = 'dj_song_list_item_votes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'song_list_item_id',
        'visitor_hash',
        'guest_pass_id',
    ];

    /**
     * @return BelongsTo<SongListItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SongListItem::class, 'song_list_item_id');
    }
}

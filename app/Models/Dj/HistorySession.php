<?php

namespace App\Models\Dj;

use App\Enums\Dj\HistorySource;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\HistorySessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A play history session: imported from Rekordbox, or plays added by hand to a gig. A session
 * can link to a gig, whose played tracks are its sessions' plays, or stay unlinked (e.g.
 * practice at home). Rekordbox starts a new session whenever it's relaunched, so one gig can
 * span several.
 *
 * @property int $id
 * @property int|null $library_id Null for manual sessions
 * @property int|null $gig_id
 * @property HistorySource $source
 * @property bool $include_in_stats
 * @property string|null $source_history_id The session's ID in the source, unique per library
 * @property string $name e.g. "HISTORY 2025-12-05 (1)"
 * @property CarbonImmutable|null $started_at The first play (UTC)
 * @property CarbonImmutable|null $ended_at The last play (UTC)
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Library|null $library
 * @property-read Gig|null $gig
 */
class HistorySession extends Model
{
    /** @use HasFactory<HistorySessionFactory> */
    use HasFactory;

    protected $table = 'dj_history_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'library_id',
        'gig_id',
        'source',
        'include_in_stats',
        'source_history_id',
        'name',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => HistorySource::class,
            'include_in_stats' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    /**
     * @return BelongsTo<Gig, $this>
     */
    public function gig(): BelongsTo
    {
        return $this->belongsTo(Gig::class);
    }

    /**
     * @return HasMany<HistoryPlay, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(HistoryPlay::class)->orderBy('position');
    }
}

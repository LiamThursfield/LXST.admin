<?php

namespace App\Models\Dj;

use Carbon\CarbonImmutable;
use Database\Factories\Dj\HistorySessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Rekordbox play history session, kept as imported, to be turned into a gig later (#118).
 * Rekordbox starts a new session whenever it's relaunched, so one set can span several.
 *
 * @property int $id
 * @property int $library_id
 * @property string $source_history_id The session's ID in the source, unique per library
 * @property string $name e.g. "HISTORY 2025-12-05 (1)"
 * @property CarbonImmutable|null $started_at The first play (UTC)
 * @property CarbonImmutable|null $ended_at The last play (UTC)
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Library $library
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
     * @return HasMany<HistoryPlay, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(HistoryPlay::class)->orderBy('position');
    }
}

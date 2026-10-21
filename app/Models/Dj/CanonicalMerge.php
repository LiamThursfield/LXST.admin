<?php

namespace App\Models\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Enums\Dj\MergeAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\CanonicalMergeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A log entry for one merge or split of canonical records, so it's clear who changed what
 *
 * @property int $id
 * @property CanonicalRecord $record
 * @property MergeAction $action
 * @property int|null $target_id The record kept (merge) or created (split)
 * @property string $target_name
 * @property int|null $source_id The record merged away (merge) or split from (split)
 * @property string $source_name
 * @property int $moved_count Library records moved to the target
 * @property int|null $user_id Null when the re-match did it automatically
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User|null $user
 */
class CanonicalMerge extends Model
{
    /** @use HasFactory<CanonicalMergeFactory> */
    use HasFactory;

    protected $table = 'dj_canonical_merges';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'record',
        'action',
        'target_id',
        'target_name',
        'source_id',
        'source_name',
        'moved_count',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record' => CanonicalRecord::class,
            'action' => MergeAction::class,
            'moved_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

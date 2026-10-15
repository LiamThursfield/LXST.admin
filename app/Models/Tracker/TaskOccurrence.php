<?php

namespace App\Models\Tracker;

use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Tracker\TaskOccurrenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_definition_id
 * @property int $user_id
 * @property string $period_key
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable|null $period_end
 * @property string|null $computed_value
 * @property TaskOccurrenceStatus $status
 * @property bool $is_holiday
 * @property CarbonImmutable|null $completed_at
 * @property-read TaskDefinition|null $taskDefinition
 * @property-read User $user
 */
class TaskOccurrence extends Model
{
    /** @use HasFactory<TaskOccurrenceFactory> */
    use HasFactory;

    protected $table = 'tracker_task_occurrences';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_definition_id',
        'user_id',
        'period_key',
        'period_start',
        'period_end',
        'computed_value',
        'status',
        'is_holiday',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'computed_value' => 'decimal:4',
            'status' => TaskOccurrenceStatus::class,
            'is_holiday' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The task definition this occurrence belongs to.
     *
     * @return BelongsTo<TaskDefinition, $this>
     */
    public function taskDefinition(): BelongsTo
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    /**
     * The user that owns this occurrence.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to the occurrences owned by the given user.
     *
     * @param  Builder<TaskOccurrence>  $query
     * @return Builder<TaskOccurrence>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}

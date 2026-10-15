<?php

namespace App\Models\Tracker;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Tracker\TaskEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_occurrence_id
 * @property int $user_id
 * @property string $value
 * @property string|null $label
 * @property CarbonImmutable $occurred_at
 * @property-read TaskOccurrence $taskOccurrence
 * @property-read User $user
 */
class TaskEntry extends Model
{
    /** @use HasFactory<TaskEntryFactory> */
    use HasFactory;

    protected $table = 'tracker_task_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_occurrence_id',
        'user_id',
        'value',
        'label',
        'occurred_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The occurrence this entry contributes to.
     *
     * @return BelongsTo<TaskOccurrence, $this>
     */
    public function taskOccurrence(): BelongsTo
    {
        return $this->belongsTo(TaskOccurrence::class);
    }

    /**
     * The user that logged the entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

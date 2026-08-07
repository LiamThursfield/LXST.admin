<?php

namespace App\Models\Tracker;

use App\Data\Tracker\TaskSchedule;
use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TrackerAggregation;
use App\Enums\Tracker\TrackerComparator;
use App\Enums\Tracker\TrackerMeasure;
use App\Enums\Tracker\TrackerPeriod;
use App\Enums\Tracker\TrackerUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Tracker\TaskDefinitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $lineage_id
 * @property int|null $category_id
 * @property string $title
 * @property string|null $description
 * @property TrackerPeriod $period
 * @property TaskSchedule|null $schedule
 * @property TrackerMeasure $measure
 * @property TrackerUnit $unit
 * @property TrackerComparator $comparator
 * @property TrackerAggregation $aggregation
 * @property string|null $target_min
 * @property string|null $target_max
 * @property bool $stepped
 * @property bool $counts_during_holiday
 * @property CarbonImmutable|null $active_from
 * @property CarbonImmutable|null $active_to
 * @property TaskDefinitionStatus $status
 * @property int $sort_order
 * @property-read Category|null $category
 * @property-read User $user
 */
class TaskDefinition extends Model
{
    /** @use HasFactory<TaskDefinitionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'tracker_task_definitions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'lineage_id',
        'category_id',
        'title',
        'description',
        'period',
        'schedule',
        'measure',
        'unit',
        'comparator',
        'aggregation',
        'target_min',
        'target_max',
        'stepped',
        'counts_during_holiday',
        'active_from',
        'active_to',
        'status',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => TrackerPeriod::class,
            'measure' => TrackerMeasure::class,
            'unit' => TrackerUnit::class,
            'comparator' => TrackerComparator::class,
            'aggregation' => TrackerAggregation::class,
            'status' => TaskDefinitionStatus::class,
            'schedule' => TaskSchedule::class,
            'stepped' => 'boolean',
            'counts_during_holiday' => 'boolean',
            'target_min' => 'decimal:4',
            'target_max' => 'decimal:4',
            'active_from' => 'date',
            'active_to' => 'date',
        ];
    }

    /**
     * The user that owns the task definition.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The category the task belongs to, if any.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope to the task definitions owned by the given user.
     *
     * @param  Builder<TaskDefinition>  $query
     * @return Builder<TaskDefinition>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * The lineage key that groups all versions of this task. The root uses its
     * own id; successors carry the root's id in lineage_id.
     */
    public function lineageKey(): int
    {
        return $this->lineage_id ?? $this->id;
    }

    /**
     * Scope to every version sharing the given definition's lineage.
     *
     * @param  Builder<TaskDefinition>  $query
     * @return Builder<TaskDefinition>
     */
    public function scopeInLineage(Builder $query, TaskDefinition $definition): Builder
    {
        $key = $definition->lineageKey();

        return $query->where(function (Builder $query) use ($key) {
            $query->where('lineage_id', $key)->orWhere('id', $key);
        });
    }
}

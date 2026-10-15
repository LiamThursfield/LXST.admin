<?php

namespace App\Services\Tracker;

use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Enums\Tracker\TrackerAggregation;
use App\Enums\Tracker\TrackerComparator;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The single source of truth for an occurrence's computed value and status.
 *
 * The value is the definition's aggregation over the occurrence's entries
 * (null when there are none). The status comes from the comparator, judged
 * against the targets and whether the window has closed:
 *
 * - While a window is open, only settled outcomes are reported early (a
 *   Boolean done, a Min reached, a Max/Between exceeded); otherwise it is
 *   Pending (no data) or OnTrack (some data).
 * - Once closed, every comparator resolves to Met or Missed, except None
 *   (metric-only), which never passes or fails.
 *
 * An empty window counts as zero only for additive aggregations (Sum, Count),
 * so "max 30 minutes of social media" with nothing logged is Met, whereas
 * "wake by 06:30" (Last) with nothing logged is Missed.
 *
 * Holidays excuse failure but never erase success: a holiday-skipped
 * occurrence is Skipped unless it evaluates to Met from logged entries. An
 * empty window on a holiday is always Skipped, even where empty would be Met.
 */
class OccurrenceEvaluator
{
    /**
     * Tolerance for comparing aggregated decimal values against targets.
     */
    private const EPSILON = 0.000001;

    /**
     * Recompute and persist the occurrence's value, status and completion time.
     */
    public function evaluate(TaskOccurrence $occurrence, ?CarbonImmutable $today = null): TaskOccurrence
    {
        $definition = $occurrence->taskDefinition;

        if ($definition === null) {
            return $occurrence;
        }

        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        /** @var Collection<int, TaskEntry> $entries */
        $entries = $occurrence->entries()->orderBy('occurred_at')->orderBy('id')->get();

        $value = $this->aggregate($definition->aggregation, $entries);
        $status = $this->status($occurrence, $definition, $value, $today);

        $occurrence->fill([
            'computed_value' => $value,
            'status' => $status,
            'completed_at' => $status === TaskOccurrenceStatus::Met
                ? ($occurrence->completed_at ?? CarbonImmutable::now())
                : null,
        ])->save();

        return $occurrence;
    }

    /**
     * Finalise every Pending / OnTrack occurrence in the current tenant whose
     * window has closed before $today. Returns the number evaluated.
     */
    public function evaluateDueForTenant(CarbonImmutable $today): int
    {
        $today = $today->startOfDay();
        $count = 0;

        TaskOccurrence::query()
            ->whereIn('status', [TaskOccurrenceStatus::Pending->value, TaskOccurrenceStatus::OnTrack->value])
            ->whereNotNull('period_end')
            ->whereDate('period_end', '<', $today)
            ->with('taskDefinition')
            ->chunkById(200, function (Collection $occurrences) use ($today, &$count): void {
                /** @var Collection<int, TaskOccurrence> $occurrences */
                foreach ($occurrences as $occurrence) {
                    $this->evaluate($occurrence, $today);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Collapse the entries into a single value using the aggregation. Entries
     * must be ordered chronologically for Last to be meaningful.
     *
     * @param  Collection<int, TaskEntry>  $entries
     */
    public function aggregate(TrackerAggregation $aggregation, Collection $entries): ?float
    {
        if ($entries->isEmpty()) {
            return null;
        }

        $values = $entries->map(fn (TaskEntry $entry): float => (float) $entry->value);

        return match ($aggregation) {
            TrackerAggregation::Sum => (float) $values->sum(),
            TrackerAggregation::Last => (float) $values->last(),
            TrackerAggregation::Min => (float) $values->min(),
            TrackerAggregation::Max => (float) $values->max(),
            TrackerAggregation::Count => (float) $values->count(),
            TrackerAggregation::Any => $values->contains(fn (float $value): bool => abs($value) > self::EPSILON) ? 1.0 : 0.0,
        };
    }

    /**
     * The status for an occurrence, applying timing and holiday precedence.
     */
    private function status(TaskOccurrence $occurrence, TaskDefinition $definition, ?float $value, CarbonImmutable $today): TaskOccurrenceStatus
    {
        $result = $occurrence->period_start->greaterThan($today)
            ? TaskOccurrenceStatus::Pending
            : $this->judge($definition, $value, $this->isClosed($occurrence, $today));

        $excused = $occurrence->is_holiday && ! $definition->counts_during_holiday;

        // A holiday keeps a Met only if something was actually logged, so an
        // empty additive window (e.g. no social media) is not credited as success.
        if ($excused && ($result !== TaskOccurrenceStatus::Met || $value === null)) {
            return TaskOccurrenceStatus::Skipped;
        }

        return $result;
    }

    /**
     * Judge a value against the definition's comparator and targets.
     */
    private function judge(TaskDefinition $definition, ?float $value, bool $closed): TaskOccurrenceStatus
    {
        $effective = $value ?? ($this->isAdditive($definition->aggregation) ? 0.0 : null);
        $min = (float) $definition->target_min;
        $max = (float) $definition->target_max;
        $open = $value === null ? TaskOccurrenceStatus::Pending : TaskOccurrenceStatus::OnTrack;

        return match ($definition->comparator) {
            TrackerComparator::None => $open,

            TrackerComparator::Boolean => match (true) {
                $effective !== null && $effective > self::EPSILON => TaskOccurrenceStatus::Met,
                $closed => TaskOccurrenceStatus::Missed,
                default => TaskOccurrenceStatus::Pending,
            },

            TrackerComparator::Min => match (true) {
                $effective !== null && $effective >= $min - self::EPSILON => TaskOccurrenceStatus::Met,
                $closed => TaskOccurrenceStatus::Missed,
                default => $open,
            },

            TrackerComparator::Max => match (true) {
                $effective !== null && $effective > $max + self::EPSILON => TaskOccurrenceStatus::Missed,
                $closed => $effective !== null ? TaskOccurrenceStatus::Met : TaskOccurrenceStatus::Missed,
                default => $open,
            },

            TrackerComparator::Between => match (true) {
                $effective !== null && $effective > $max + self::EPSILON => TaskOccurrenceStatus::Missed,
                $closed => $effective !== null && $effective >= $min - self::EPSILON
                    ? TaskOccurrenceStatus::Met
                    : TaskOccurrenceStatus::Missed,
                default => $open,
            },
        };
    }

    /**
     * Whether the occurrence's window ended before $today. Open-ended
     * (lifetime) windows never close.
     */
    private function isClosed(TaskOccurrence $occurrence, CarbonImmutable $today): bool
    {
        return $occurrence->period_end !== null && $occurrence->period_end->lessThan($today);
    }

    /**
     * Whether an empty window should be treated as zero.
     */
    private function isAdditive(TrackerAggregation $aggregation): bool
    {
        return in_array($aggregation, [TrackerAggregation::Sum, TrackerAggregation::Count], true);
    }
}

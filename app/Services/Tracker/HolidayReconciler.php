<?php

namespace App\Services\Tracker;

use App\Models\Tracker\Holiday;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Re-marks a user's existing occurrences after their holidays change (added,
 * edited or removed), since generation only marks holidays at creation time.
 *
 * It only refreshes the is_holiday flag; the resulting status is decided by
 * the OccurrenceEvaluator, so holiday precedence lives in one place.
 */
class HolidayReconciler
{
    public function __construct(private OccurrenceEvaluator $evaluator) {}

    /**
     * Reconcile every occurrence owned by the user against their current
     * holidays. Returns the number of occurrences changed.
     */
    public function reconcileForUser(User $user): int
    {
        /** @var Collection<int, Holiday> $holidays */
        $holidays = Holiday::query()->where('user_id', $user->id)->get();

        $changed = 0;

        TaskOccurrence::query()
            ->where('user_id', $user->id)
            ->with('taskDefinition')
            ->chunkById(200, function (Collection $occurrences) use ($holidays, &$changed): void {
                /** @var Collection<int, TaskOccurrence> $occurrences */
                foreach ($occurrences as $occurrence) {
                    if ($this->reconcile($occurrence, $holidays)) {
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    /**
     * Refresh a single occurrence; returns whether it changed.
     *
     * @param  Collection<int, Holiday>  $holidays
     */
    private function reconcile(TaskOccurrence $occurrence, Collection $holidays): bool
    {
        // A soft-deleted definition leaves orphan occurrences we should not touch.
        if ($occurrence->taskDefinition === null) {
            return false;
        }

        $holidayBefore = $occurrence->is_holiday;
        $statusBefore = $occurrence->status;

        $occurrence->is_holiday = $this->isCovered($occurrence, $holidays);

        $this->evaluator->evaluate($occurrence);

        return $holidayBefore !== $occurrence->is_holiday
            || $statusBefore !== $occurrence->status;
    }

    /**
     * Whether the occurrence's window is fully covered by one of the holidays.
     *
     * @param  Collection<int, Holiday>  $holidays
     */
    private function isCovered(TaskOccurrence $occurrence, Collection $holidays): bool
    {
        $end = $occurrence->period_end;

        if ($end === null) {
            return false;
        }

        $start = $occurrence->period_start;

        foreach ($holidays as $holiday) {
            if ($holiday->starts_on->lessThanOrEqualTo($start)
                && $holiday->ends_on->greaterThanOrEqualTo($end)) {
                return true;
            }
        }

        return false;
    }
}

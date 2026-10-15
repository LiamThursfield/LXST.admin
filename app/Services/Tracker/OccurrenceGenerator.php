<?php

namespace App\Services\Tracker;

use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Enums\Tracker\TrackerPeriod;
use App\Models\Tracker\Holiday;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Materialises task occurrences (one per active period window) for task
 * definitions, up to a given date. Generation is idempotent — the unique
 * (task_definition_id, period_key) constraint means an occurrence is only ever
 * created once, so the command can safely run every day and self-heal gaps.
 * Existing occurrences are never modified here, so cached evaluation state is
 * left untouched.
 */
class OccurrenceGenerator
{
    public function __construct(private PeriodCalculator $calculator) {}

    /**
     * Generate every missing occurrence for one definition up to $upTo.
     * Returns the number of occurrences created.
     */
    public function generateForDefinition(TaskDefinition $definition, CarbonImmutable $upTo): int
    {
        if ($definition->status !== TaskDefinitionStatus::Active) {
            return 0;
        }

        $activeFrom = $definition->active_from?->startOfDay();

        if ($activeFrom === null) {
            return 0;
        }

        $activeTo = $definition->active_to?->startOfDay();
        $upTo = $upTo->startOfDay();

        // The task has not started yet within the generation horizon.
        if ($activeFrom->greaterThan($upTo)) {
            return 0;
        }

        $holidays = $this->holidaysFor($definition);

        if ($definition->period === TrackerPeriod::Lifetime) {
            return $this->upsert($definition, new PeriodWindow('lifetime', $activeFrom, $activeTo), $holidays);
        }

        if ($definition->period === TrackerPeriod::OneOff) {
            return $this->upsert($definition, new PeriodWindow('oneoff', $activeFrom, $activeTo ?? $activeFrom), $holidays);
        }

        // Recurring: cap the horizon by active_to.
        $genEnd = $activeTo !== null && $activeTo->lessThan($upTo) ? $activeTo : $upTo;

        $created = 0;
        $cursor = $this->calculator->window($definition->period, $activeFrom)->start;

        while ($cursor->lessThanOrEqualTo($genEnd)) {
            $window = $this->calculator->window($definition->period, $cursor);

            if ($this->windowOverlapsActive($window, $activeFrom, $activeTo)
                && $this->scheduleAllows($definition, $window)) {
                $created += $this->upsert($definition, $window, $holidays);
            }

            $cursor = $this->calculator->next($definition->period, $cursor);
        }

        return $created;
    }

    /**
     * Generate occurrences for every active definition in the current tenant.
     * Returns the total number of occurrences created.
     */
    public function generateForTenant(CarbonImmutable $upTo): int
    {
        $count = 0;

        TaskDefinition::query()
            ->where('status', TaskDefinitionStatus::Active->value)
            ->chunkById(100, function (Collection $definitions) use (&$count, $upTo): void {
                /** @var Collection<int, TaskDefinition> $definitions */
                foreach ($definitions as $definition) {
                    $count += $this->generateForDefinition($definition, $upTo);
                }
            });

        return $count;
    }

    /**
     * Lazily ensure the occurrence for the window containing $date exists,
     * returning it (or null if $date falls outside the definition's active
     * range or is filtered out by its schedule). Used when logging progress.
     */
    public function ensureOccurrence(TaskDefinition $definition, CarbonImmutable $date): ?TaskOccurrence
    {
        $date = $date->startOfDay();
        $activeFrom = $definition->active_from?->startOfDay();
        $activeTo = $definition->active_to?->startOfDay();

        if ($activeFrom === null || $date->lessThan($activeFrom)) {
            return null;
        }

        if ($activeTo !== null && $date->greaterThan($activeTo)) {
            return null;
        }

        $window = match ($definition->period) {
            TrackerPeriod::Lifetime => new PeriodWindow('lifetime', $activeFrom, $activeTo),
            TrackerPeriod::OneOff => new PeriodWindow('oneoff', $activeFrom, $activeTo ?? $activeFrom),
            default => $this->calculator->window($definition->period, $date),
        };

        if ($this->calculator->isRecurring($definition->period) && ! $this->scheduleAllows($definition, $window)) {
            return null;
        }

        $this->upsert($definition, $window, $this->holidaysFor($definition));

        return TaskOccurrence::query()
            ->where('task_definition_id', $definition->id)
            ->where('period_key', $window->key)
            ->first();
    }

    /**
     * Create the occurrence for the window if it does not already exist.
     * Returns 1 when created, 0 when it was already present.
     *
     * @param  Collection<int, Holiday>  $holidays
     */
    private function upsert(TaskDefinition $definition, PeriodWindow $window, Collection $holidays): int
    {
        $isHoliday = $this->windowIsHoliday($window, $holidays);
        $skipped = $isHoliday && ! $definition->counts_during_holiday;

        $occurrence = TaskOccurrence::firstOrCreate(
            [
                'task_definition_id' => $definition->id,
                'period_key' => $window->key,
            ],
            [
                'user_id' => $definition->user_id,
                'period_start' => $window->start->toDateString(),
                'period_end' => $window->end?->toDateString(),
                'is_holiday' => $isHoliday,
                'status' => $skipped ? TaskOccurrenceStatus::Skipped : TaskOccurrenceStatus::Pending,
            ],
        );

        return $occurrence->wasRecentlyCreated ? 1 : 0;
    }

    /**
     * Whether the window overlaps the definition's active date range.
     */
    private function windowOverlapsActive(PeriodWindow $window, CarbonImmutable $activeFrom, ?CarbonImmutable $activeTo): bool
    {
        $end = $window->end ?? $window->start;

        if ($end->lessThan($activeFrom)) {
            return false;
        }

        return $activeTo === null || $window->start->lessThanOrEqualTo($activeTo);
    }

    /**
     * Whether the definition's schedule permits a window. daysOfWeek /
     * daysOfMonth apply to Daily; monthsOfYear applies to Daily and Monthly.
     * Weekly and Yearly are not schedule-filtered in v1.
     */
    private function scheduleAllows(TaskDefinition $definition, PeriodWindow $window): bool
    {
        $schedule = $definition->schedule;

        if ($schedule === null) {
            return true;
        }

        return match ($definition->period) {
            TrackerPeriod::Daily => $schedule->matches($window->start),
            TrackerPeriod::Monthly => $schedule->monthsOfYear === null
                || in_array($window->start->month, $schedule->monthsOfYear, true),
            default => true,
        };
    }

    /**
     * Whether the window is fully covered by one of the user's holidays.
     *
     * @param  Collection<int, Holiday>  $holidays
     */
    private function windowIsHoliday(PeriodWindow $window, Collection $holidays): bool
    {
        if ($window->end === null) {
            return false;
        }

        foreach ($holidays as $holiday) {
            if ($holiday->starts_on->lessThanOrEqualTo($window->start)
                && $holiday->ends_on->greaterThanOrEqualTo($window->end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Holiday>
     */
    private function holidaysFor(TaskDefinition $definition): Collection
    {
        return Holiday::query()->where('user_id', $definition->user_id)->get();
    }
}

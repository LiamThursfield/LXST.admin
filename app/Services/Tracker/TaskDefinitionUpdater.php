<?php

namespace App\Services\Tracker;

use App\Data\Tracker\TaskSchedule;
use App\Enums\Tracker\TaskDefinitionStatus;
use App\Models\Tracker\TaskDefinition;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies an edit to a task definition, deciding whether the change is cosmetic
 * (update in place) or semantic (supersede the old version with a new one).
 *
 * A semantic change alters what counts as "met" — the period, measure,
 * comparator, aggregation, targets, or schedule. When one changes we keep the
 * old version for history (archived, end-dated today) and create a linked new
 * version so past occurrences stay judged under the rule they were logged
 * against. Cosmetic changes (title, description, category, unit, dates, status)
 * are applied to the same row.
 */
class TaskDefinitionUpdater
{
    /**
     * Scalar fields whose change is semantic. Targets and schedule are compared
     * separately because they need numeric / structural comparison.
     *
     * @var list<string>
     */
    private const SEMANTIC_FIELDS = [
        'period',
        'measure',
        'comparator',
        'aggregation',
    ];

    /**
     * Apply the given attributes to the definition, returning the current
     * (possibly newly created) version.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(TaskDefinition $definition, array $attributes): TaskDefinition
    {
        if ($this->isSemanticChange($definition, $attributes)) {
            return $this->supersede($definition, $attributes);
        }

        $definition->update($attributes);

        return $definition;
    }

    /**
     * Whether any semantic field differs between the definition and the edit.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function isSemanticChange(TaskDefinition $definition, array $attributes): bool
    {
        foreach (self::SEMANTIC_FIELDS as $field) {
            /** @var BackedEnum $current these fields are all enum-cast */
            $current = $definition->{$field};

            if ($current->value !== (string) ($attributes[$field] ?? null)) {
                return true;
            }
        }

        if (! $this->sameNumber($definition->target_min, $attributes['target_min'] ?? null)) {
            return true;
        }

        if (! $this->sameNumber($definition->target_max, $attributes['target_max'] ?? null)) {
            return true;
        }

        return $this->scheduleSignature($definition->schedule)
            !== $this->scheduleSignature($attributes['schedule'] ?? null);
    }

    /**
     * Archive the old version end-dated today and create a linked new version
     * starting today with the edited attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function supersede(TaskDefinition $old, array $attributes): TaskDefinition
    {
        return DB::transaction(function () use ($old, $attributes): TaskDefinition {
            $today = CarbonImmutable::today();

            $new = TaskDefinition::create([
                ...$attributes,
                'user_id' => $old->user_id,
                'lineage_id' => $old->lineageKey(),
                'active_from' => $today,
            ]);

            $old->update([
                'status' => TaskDefinitionStatus::Archived->value,
                'active_to' => $today,
            ]);

            return $new;
        });
    }

    /**
     * Compare two nullable numeric targets with a small tolerance.
     */
    private function sameNumber(int|float|string|null $a, int|float|string|null $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs((float) $a - (float) $b) < 0.0001;
    }

    /**
     * A normalised, order-insensitive signature of a schedule. An empty or
     * all-null schedule collapses to `[]` so it compares equal to no schedule.
     *
     * @return array<string, mixed>
     */
    private function scheduleSignature(?TaskSchedule $schedule): array
    {
        if ($schedule === null) {
            return [];
        }

        $days = $this->sortedInts($schedule->daysOfWeek);
        $months = $this->sortedInts($schedule->monthsOfYear);
        $daysOfMonth = $this->sortedInts($schedule->daysOfMonth);
        $interval = $schedule->interval;

        if ($days === [] && $months === [] && $daysOfMonth === [] && $interval === 1) {
            return [];
        }

        return [
            'daysOfWeek' => $days,
            'monthsOfYear' => $months,
            'daysOfMonth' => $daysOfMonth,
            'interval' => $interval,
        ];
    }

    /**
     * @param  int[]|null  $values
     * @return int[]
     */
    private function sortedInts(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        sort($values);

        return $values;
    }
}

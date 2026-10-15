<?php

use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Enums\Tracker\TrackerAggregation;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use App\Services\Tracker\OccurrenceEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Definition attributes for each scenario shape. Targets are in base units.
 *
 * @return array<string, mixed>
 */
function definitionShape(string $shape): array
{
    return match ($shape) {
        // "Did you work out?"
        'boolean' => ['measure' => 'boolean', 'unit' => 'boolean', 'comparator' => 'boolean', 'aggregation' => 'any', 'target_min' => null, 'target_max' => null],
        // "Drink at least 2L" (ml)
        'min-sum' => ['measure' => 'volume', 'unit' => 'ml', 'comparator' => 'min', 'aggregation' => 'sum', 'target_min' => 2000, 'target_max' => null],
        // "Max 30 minutes of social media" (seconds)
        'max-sum' => ['measure' => 'duration', 'unit' => 'minutes', 'comparator' => 'max', 'aggregation' => 'sum', 'target_min' => null, 'target_max' => 1800],
        // "Wake up by 06:30" (seconds since midnight)
        'max-last' => ['measure' => 'clock-time', 'unit' => 'time-of-day', 'comparator' => 'max', 'aggregation' => 'last', 'target_min' => null, 'target_max' => 23400],
        // "Eat between 1500 and 1750 kcal"
        'between-sum' => ['measure' => 'energy', 'unit' => 'kcal', 'comparator' => 'between', 'aggregation' => 'sum', 'target_min' => 1500, 'target_max' => 1750],
        // "Current weight" (grams), metric only
        'metric' => ['measure' => 'weight', 'unit' => 'kg', 'comparator' => 'none', 'aggregation' => 'last', 'target_min' => null, 'target_max' => null],
    };
}

/**
 * Build a 2026-08-03 occurrence of the given shape with entries (base units,
 * logged in order), evaluate it as of $today and return the fresh model.
 *
 * @param  list<int|float>  $values
 * @param  array<string, mixed>  $definitionOverrides
 * @param  array<string, mixed>  $occurrenceOverrides
 */
function evaluateScenario(string $shape, array $values, string $today, array $definitionOverrides = [], array $occurrenceOverrides = []): TaskOccurrence
{
    $definition = TaskDefinition::factory()->create([
        ...definitionShape($shape),
        'active_from' => '2026-08-01',
        ...$definitionOverrides,
    ]);

    $occurrence = TaskOccurrence::factory()->forDefinition($definition)->create([
        'period_key' => '2026-08-03',
        'period_start' => '2026-08-03',
        'period_end' => '2026-08-03',
        'status' => TaskOccurrenceStatus::Pending,
        'is_holiday' => false,
        ...$occurrenceOverrides,
    ]);

    foreach ($values as $index => $value) {
        TaskEntry::factory()->forOccurrence($occurrence)->create([
            'value' => $value,
            'occurred_at' => CarbonImmutable::parse('2026-08-03 08:00')->addMinutes($index),
        ]);
    }

    return app(OccurrenceEvaluator::class)
        ->evaluate($occurrence, CarbonImmutable::parse($today))
        ->fresh();
}

const EVALUATION_OPEN_DAY = '2026-08-03';
const EVALUATION_CLOSED_DAY = '2026-08-04';

test('it judges each comparator against its targets and timing', function (string $shape, array $values, string $today, TaskOccurrenceStatus $expected) {
    expect(evaluateScenario($shape, $values, $today)->status)->toBe($expected);
})->with([
    'boolean done' => ['boolean', [1], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::Met],
    'boolean open, nothing logged' => ['boolean', [], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::Pending],
    'boolean closed, nothing logged' => ['boolean', [], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Missed],

    'min open, partway' => ['min-sum', [500, 700], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::OnTrack],
    'min open, reached' => ['min-sum', [1500, 600], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::Met],
    'min closed, short' => ['min-sum', [1200], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Missed],
    'min closed, nothing logged' => ['min-sum', [], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Missed],

    'max (sum) closed, nothing logged counts as zero' => ['max-sum', [], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Met],
    'max (sum) open, under' => ['max-sum', [1200], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::OnTrack],
    'max (sum) open, exceeded' => ['max-sum', [1000, 1000], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::Missed],

    'max (last) closed, nothing logged is missed' => ['max-last', [], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Missed],
    'max (last) closed, under' => ['max-last', [22000], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Met],
    'max (last) uses the latest entry' => ['max-last', [25000, 22000], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Met],
    'max (last) latest entry exceeds' => ['max-last', [22000, 25000], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Missed],

    'between open, in range' => ['between-sum', [1600], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::OnTrack],
    'between open, exceeded' => ['between-sum', [1000, 900], EVALUATION_OPEN_DAY, TaskOccurrenceStatus::Missed],
    'between closed, in range' => ['between-sum', [1600], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Met],
    'between closed, under' => ['between-sum', [1400], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Missed],

    'metric with data' => ['metric', [80000], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::OnTrack],
    'metric without data' => ['metric', [], EVALUATION_CLOSED_DAY, TaskOccurrenceStatus::Pending],
]);

test('it stores the aggregated value', function () {
    expect((float) evaluateScenario('min-sum', [500, 700], EVALUATION_OPEN_DAY)->computed_value)->toBe(1200.0)
        ->and(evaluateScenario('min-sum', [], EVALUATION_OPEN_DAY)->computed_value)->toBeNull();
});

test('it aggregates entries with each aggregation', function (TrackerAggregation $aggregation, array $values, ?float $expected) {
    $entries = new Collection(array_map(fn ($value) => new TaskEntry(['value' => $value]), $values));

    expect(app(OccurrenceEvaluator::class)->aggregate($aggregation, $entries))->toBe($expected);
})->with([
    'sum' => [TrackerAggregation::Sum, [2, 3, 5], 10.0],
    'last' => [TrackerAggregation::Last, [2, 3, 5], 5.0],
    'min' => [TrackerAggregation::Min, [4, 2, 5], 2.0],
    'max' => [TrackerAggregation::Max, [4, 2, 5], 5.0],
    'count' => [TrackerAggregation::Count, [4, 2, 5], 3.0],
    'any with a truthy entry' => [TrackerAggregation::Any, [0, 1], 1.0],
    'any with only zeroes' => [TrackerAggregation::Any, [0], 0.0],
    'empty' => [TrackerAggregation::Sum, [], null],
]);

test('a holiday excuses a failed occurrence', function () {
    $occurrence = evaluateScenario('min-sum', [], EVALUATION_CLOSED_DAY, ['counts_during_holiday' => false], ['is_holiday' => true]);

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Skipped);
});

test('a holiday never erases a met occurrence', function () {
    $occurrence = evaluateScenario('min-sum', [2000], EVALUATION_CLOSED_DAY, ['counts_during_holiday' => false], ['is_holiday' => true]);

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Met);
});

test('a holiday does not credit an empty window as met', function () {
    // An empty "max social media" would be Met on a normal day.
    $occurrence = evaluateScenario('max-sum', [], EVALUATION_CLOSED_DAY, ['counts_during_holiday' => false], ['is_holiday' => true]);

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Skipped);
});

test('a holiday keeps a met max when something was logged', function () {
    $occurrence = evaluateScenario('max-sum', [600], EVALUATION_CLOSED_DAY, ['counts_during_holiday' => false], ['is_holiday' => true]);

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Met);
});

test('a holiday is ignored when the task counts during holidays', function () {
    $occurrence = evaluateScenario('min-sum', [], EVALUATION_CLOSED_DAY, ['counts_during_holiday' => true], ['is_holiday' => true]);

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Missed);
});

test('a window that has not started is pending', function () {
    expect(evaluateScenario('boolean', [], '2026-08-02')->status)->toBe(TaskOccurrenceStatus::Pending);
});

test('an open-ended lifetime window never closes', function () {
    $occurrence = evaluateScenario('boolean', [], '2030-01-01', [], [
        'period_key' => 'lifetime',
        'period_end' => null,
    ]);

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Pending);
});

test('completed_at is set when met and cleared when no longer met', function () {
    $occurrence = evaluateScenario('min-sum', [2000], EVALUATION_OPEN_DAY);
    expect($occurrence->completed_at)->not->toBeNull();

    $occurrence->entries()->delete();
    $occurrence = app(OccurrenceEvaluator::class)->evaluate($occurrence, CarbonImmutable::parse(EVALUATION_OPEN_DAY))->fresh();

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Pending)
        ->and($occurrence->completed_at)->toBeNull();
});

test('evaluateDueForTenant finalises only occurrences whose period has ended', function () {
    $missed = evaluateScenario('min-sum', [], EVALUATION_OPEN_DAY);      // Pending, closes after 08-03
    $met = evaluateScenario('max-sum', [], EVALUATION_OPEN_DAY);         // Pending, empty additive max
    $open = evaluateScenario('min-sum', [], EVALUATION_OPEN_DAY, [], [   // still open on 08-04
        'period_key' => '2026-08-04',
        'period_start' => '2026-08-04',
        'period_end' => '2026-08-04',
    ]);

    $evaluated = app(OccurrenceEvaluator::class)->evaluateDueForTenant(CarbonImmutable::parse('2026-08-04'));

    expect($evaluated)->toBe(2)
        ->and($missed->fresh()->status)->toBe(TaskOccurrenceStatus::Missed)
        ->and($met->fresh()->status)->toBe(TaskOccurrenceStatus::Met)
        ->and($open->fresh()->status)->toBe(TaskOccurrenceStatus::Pending);
});

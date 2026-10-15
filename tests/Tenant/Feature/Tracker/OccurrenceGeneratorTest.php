<?php

use App\Data\Tracker\TaskSchedule;
use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Enums\Tracker\TrackerPeriod;
use App\Models\Tracker\Holiday;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use App\Services\Tracker\OccurrenceGenerator;
use Carbon\CarbonImmutable;

function generator(): OccurrenceGenerator
{
    return app(OccurrenceGenerator::class);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeTask(User $user, array $attributes = []): TaskDefinition
{
    return TaskDefinition::factory()->forUser($user)->create(array_merge([
        'period' => TrackerPeriod::Daily,
        'active_from' => '2026-08-01',
        'active_to' => null,
        'schedule' => null,
        'status' => TaskDefinitionStatus::Active,
        'counts_during_holiday' => false,
    ], $attributes));
}

function generateUpTo(TaskDefinition $task, string $upTo): int
{
    return generator()->generateForDefinition($task, CarbonImmutable::parse($upTo));
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('a daily task generates one occurrence per day in range', function () {
    $task = makeTask($this->user, ['active_from' => '2026-08-01']);

    $created = generateUpTo($task, '2026-08-05');

    expect($created)->toBe(5)
        ->and(TaskOccurrence::count())->toBe(5)
        ->and(TaskOccurrence::orderBy('period_key')->pluck('period_key')->all())
        ->toBe(['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05']);
});

test('generation is idempotent', function () {
    $task = makeTask($this->user, ['active_from' => '2026-08-01']);

    generateUpTo($task, '2026-08-05');
    $secondRun = generateUpTo($task, '2026-08-05');

    expect($secondRun)->toBe(0)
        ->and(TaskOccurrence::count())->toBe(5);
});

test('generation stops at the active_to date', function () {
    $task = makeTask($this->user, ['active_from' => '2026-08-01', 'active_to' => '2026-08-03']);

    $created = generateUpTo($task, '2026-08-10');

    expect($created)->toBe(3);
});

test('a daily schedule only generates the allowed weekdays', function () {
    // 2026-08-03 is a Monday.
    $task = makeTask($this->user, [
        'active_from' => '2026-08-03',
        'schedule' => TaskSchedule::from(['daysOfWeek' => [1, 3, 5]]),
    ]);

    generateUpTo($task, '2026-08-09'); // Mon–Sun

    expect(TaskOccurrence::orderBy('period_key')->pluck('period_key')->all())
        ->toBe(['2026-08-03', '2026-08-05', '2026-08-07']); // Mon, Wed, Fri
});

test('a weekly task generates one occurrence per ISO week', function () {
    $task = makeTask($this->user, ['period' => TrackerPeriod::Weekly, 'active_from' => '2026-08-01']);

    $created = generateUpTo($task, '2026-08-20');

    // Weeks W31 (contains Aug 1), W32, W33, W34.
    expect($created)->toBe(4)
        ->and(TaskOccurrence::orderBy('period_key')->pluck('period_key')->all())
        ->toBe(['2026-W31', '2026-W32', '2026-W33', '2026-W34']);
});

test('a monthly task honours the months-of-year schedule', function () {
    $task = makeTask($this->user, [
        'period' => TrackerPeriod::Monthly,
        'active_from' => '2026-01-01',
        'active_to' => '2026-12-31',
        'schedule' => TaskSchedule::from(['monthsOfYear' => [6, 7, 8]]),
    ]);

    generateUpTo($task, '2026-12-31');

    expect(TaskOccurrence::orderBy('period_key')->pluck('period_key')->all())
        ->toBe(['2026-06', '2026-07', '2026-08']);
});

test('a lifetime task generates a single open-ended occurrence', function () {
    $task = makeTask($this->user, ['period' => TrackerPeriod::Lifetime, 'active_from' => '2026-08-01', 'active_to' => null]);

    $created = generateUpTo($task, '2026-12-31');

    $occurrence = TaskOccurrence::sole();
    expect($created)->toBe(1)
        ->and($occurrence->period_key)->toBe('lifetime')
        ->and($occurrence->period_end)->toBeNull();
});

test('a one-off task generates a single occurrence', function () {
    $task = makeTask($this->user, ['period' => TrackerPeriod::OneOff, 'active_from' => '2026-08-01', 'active_to' => '2026-08-10']);

    $created = generateUpTo($task, '2026-12-31');

    expect($created)->toBe(1)
        ->and(TaskOccurrence::sole()->period_key)->toBe('oneoff');
});

test('paused and archived tasks generate nothing', function () {
    $paused = makeTask($this->user, ['status' => TaskDefinitionStatus::Paused]);
    $archived = makeTask($this->user, ['status' => TaskDefinitionStatus::Archived]);

    expect(generateUpTo($paused, '2026-08-05'))->toBe(0)
        ->and(generateUpTo($archived, '2026-08-05'))->toBe(0)
        ->and(TaskOccurrence::count())->toBe(0);
});

test('occurrences within a holiday are skipped when the task does not count during holidays', function () {
    Holiday::factory()->forUser($this->user)->create([
        'starts_on' => '2026-08-02',
        'ends_on' => '2026-08-04',
    ]);

    $task = makeTask($this->user, ['active_from' => '2026-08-01', 'counts_during_holiday' => false]);

    generateUpTo($task, '2026-08-05');

    $skipped = TaskOccurrence::where('status', TaskOccurrenceStatus::Skipped->value)
        ->pluck('period_key')->all();

    expect($skipped)->toBe(['2026-08-02', '2026-08-03', '2026-08-04'])
        ->and(TaskOccurrence::where('period_key', '2026-08-01')->sole()->status)
        ->toBe(TaskOccurrenceStatus::Pending);
});

test('holiday occurrences are still pending when the task counts during holidays', function () {
    Holiday::factory()->forUser($this->user)->create([
        'starts_on' => '2026-08-02',
        'ends_on' => '2026-08-04',
    ]);

    $task = makeTask($this->user, ['active_from' => '2026-08-01', 'counts_during_holiday' => true]);

    generateUpTo($task, '2026-08-05');

    $occurrence = TaskOccurrence::where('period_key', '2026-08-03')->sole();
    expect($occurrence->is_holiday)->toBeTrue()
        ->and($occurrence->status)->toBe(TaskOccurrenceStatus::Pending);
});

test('generateForTenant covers every active definition', function () {
    makeTask($this->user, ['active_from' => '2026-08-01', 'active_to' => '2026-08-02']); // 2
    makeTask(User::factory()->create(), ['active_from' => '2026-08-01', 'active_to' => '2026-08-01']); // 1
    makeTask($this->user, ['status' => TaskDefinitionStatus::Paused]); // 0

    $created = generator()->generateForTenant(CarbonImmutable::parse('2026-08-05'));

    expect($created)->toBe(3);
});

describe('ensureOccurrence', function () {
    it('lazily creates the occurrence for a date', function () {
        $task = makeTask($this->user, ['active_from' => '2026-08-01']);

        $occurrence = generator()->ensureOccurrence($task, CarbonImmutable::parse('2026-08-03'));

        expect($occurrence)->not->toBeNull()
            ->and($occurrence->period_key)->toBe('2026-08-03')
            ->and(TaskOccurrence::count())->toBe(1);
    });

    it('returns null for a date outside the active range', function () {
        $task = makeTask($this->user, ['active_from' => '2026-08-01', 'active_to' => '2026-08-05']);

        expect(generator()->ensureOccurrence($task, CarbonImmutable::parse('2026-07-31')))->toBeNull()
            ->and(generator()->ensureOccurrence($task, CarbonImmutable::parse('2026-08-06')))->toBeNull()
            ->and(TaskOccurrence::count())->toBe(0);
    });

    it('returns null for a date the schedule excludes', function () {
        // 2026-08-03 is a Monday; Tuesday 2026-08-04 is excluded.
        $task = makeTask($this->user, [
            'active_from' => '2026-08-03',
            'schedule' => TaskSchedule::from(['daysOfWeek' => [1]]), // Mondays only
        ]);

        expect(generator()->ensureOccurrence($task, CarbonImmutable::parse('2026-08-04')))->toBeNull()
            ->and(generator()->ensureOccurrence($task, CarbonImmutable::parse('2026-08-03')))->not->toBeNull();
    });
});

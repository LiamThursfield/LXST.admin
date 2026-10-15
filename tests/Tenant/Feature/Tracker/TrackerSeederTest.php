<?php

use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Models\Tracker\Category;
use App\Models\Tracker\Holiday;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\TrackerSeeder;

beforeEach(function () {
    // A Wednesday; history therefore starts on Thursday 2026-07-30.
    $this->travelTo(CarbonImmutable::parse('2026-08-26 12:00'));
});

function seedTrackerFor(?User $user = null): void
{
    app(TrackerSeeder::class)->run($user);
}

function seededTask(User $user, string $title): TaskDefinition
{
    return TaskDefinition::ownedBy($user)->where('title', $title)->sole();
}

test('it seeds the example habits, categories and a holiday', function () {
    $user = User::factory()->create();

    seedTrackerFor($user);

    expect(TaskDefinition::ownedBy($user)->count())->toBe(8)
        ->and(Category::where('is_public', true)->whereNull('user_id')->pluck('name')->sort()->values()->all())
        ->toBe(['Fitness', 'Health', 'Productivity', 'Wellbeing'])
        ->and(Holiday::ownedBy($user)->count())->toBe(1);
});

test('every seeded task is internally consistent', function () {
    $user = User::factory()->create();

    seedTrackerFor($user);

    foreach (TaskDefinition::ownedBy($user)->get() as $task) {
        expect($task->unit->measure())->toBe($task->measure, "{$task->title} unit/measure mismatch")
            ->and($task->category_id)->not->toBeNull();

        if ($task->comparator->requiresMin()) {
            expect($task->target_min)->not->toBeNull("{$task->title} needs a min target");
        }

        if ($task->comparator->requiresMax()) {
            expect($task->target_max)->not->toBeNull("{$task->title} needs a max target");
        }
    }
});

test('it builds four weeks of evaluated history', function () {
    $user = User::factory()->create();

    seedTrackerFor($user);

    $water = seededTask($user, 'Drink at least 2 litres of water');

    // 2026-07-30 to 2026-08-26 inclusive.
    expect(TaskOccurrence::where('task_definition_id', $water->id)->count())->toBe(28)
        ->and(TaskEntry::count())->toBeGreaterThan(200);

    $statuses = TaskOccurrence::ownedBy($user)->pluck('status')->unique();

    expect($statuses)->toContain(TaskOccurrenceStatus::Met)
        ->and($statuses)->toContain(TaskOccurrenceStatus::Missed)
        ->and($statuses)->toContain(TaskOccurrenceStatus::Skipped);
});

test('holiday days are skipped and nothing is logged on them', function () {
    $user = User::factory()->create();

    seedTrackerFor($user);

    $water = seededTask($user, 'Drink at least 2 litres of water');

    // Holiday runs 2026-08-14 to 2026-08-16.
    $holidayDays = TaskOccurrence::where('task_definition_id', $water->id)
        ->whereBetween('period_start', ['2026-08-14', '2026-08-16'])
        ->get();

    expect($holidayDays)->toHaveCount(3)
        ->and($holidayDays->every(fn (TaskOccurrence $occurrence) => $occurrence->status === TaskOccurrenceStatus::Skipped))->toBeTrue()
        ->and($holidayDays->sum(fn (TaskOccurrence $occurrence) => $occurrence->entries()->count()))->toBe(0);
});

test('the workout only has occurrences on Mondays, Wednesdays and Fridays', function () {
    $user = User::factory()->create();

    seedTrackerFor($user);

    $days = TaskOccurrence::where('task_definition_id', seededTask($user, 'Workout')->id)
        ->get()
        ->map(fn (TaskOccurrence $occurrence) => $occurrence->period_start->isoWeekday())
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($days)->toBe([1, 3, 5]);
});

test('today is only partly logged', function () {
    $user = User::factory()->create();

    seedTrackerFor($user);

    $water = TaskOccurrence::where('task_definition_id', seededTask($user, 'Drink at least 2 litres of water')->id)
        ->where('period_key', '2026-08-26')
        ->sole();

    expect((float) $water->computed_value)->toBe(500.0)
        ->and($water->status)->toBe(TaskOccurrenceStatus::OnTrack);
});

test('re-running for the same user does not duplicate anything', function () {
    $user = User::factory()->create();

    seedTrackerFor($user);
    $entries = TaskEntry::count();

    seedTrackerFor($user);

    expect(TaskDefinition::ownedBy($user)->count())->toBe(8)
        ->and(Category::where('is_public', true)->count())->toBe(4)
        ->and(TaskEntry::count())->toBe($entries);
});

test('it seeds the first user when none is given', function () {
    $first = User::factory()->create();
    User::factory()->create();

    seedTrackerFor();

    expect(TaskDefinition::ownedBy($first)->count())->toBe(8);
});

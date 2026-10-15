<?php

use App\Data\Tracker\TaskSchedule;
use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-05 12:00'));

    $this->user = $this->createUserWithPermissions([TrackerPermission::LogEntries]);
    $this->actingAs($this->user);

    // Daily "drink at least 2 litres" goal, logged in litres, stored in ml.
    $this->task = TaskDefinition::factory()->forUser($this->user)->create([
        'active_from' => '2026-08-01',
        'unit' => 'l',
        'target_min' => 2000,
        'stepped' => true,
    ]);
});

function logEntry(TaskDefinition $task, array $payload): TestResponse
{
    return test()->post(route('admin.tracker.tasks.entries.store', $task), $payload);
}

function occurrenceFor(TaskDefinition $task, string $date): TaskOccurrence
{
    return TaskOccurrence::where('task_definition_id', $task->id)->where('period_key', $date)->sole();
}

test('logging progress creates the occurrence and stores the value in base units', function () {
    logEntry($this->task, ['value' => 1.5, 'occurred_at' => '2026-08-05 09:00'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Progress logged.');

    $occurrence = occurrenceFor($this->task, '2026-08-05');

    expect((float) TaskEntry::sole()->value)->toBe(1500.0)
        ->and((float) $occurrence->computed_value)->toBe(1500.0)
        ->and($occurrence->status)->toBe(TaskOccurrenceStatus::OnTrack);
});

test('reaching the target marks the occurrence met', function () {
    logEntry($this->task, ['value' => 1, 'occurred_at' => '2026-08-05 09:00']);
    logEntry($this->task, ['value' => 1, 'occurred_at' => '2026-08-05 11:00']);

    $occurrence = occurrenceFor($this->task, '2026-08-05');

    expect($occurrence->status)->toBe(TaskOccurrenceStatus::Met)
        ->and($occurrence->completed_at)->not->toBeNull();
});

test('backfilling a past day can turn a missed occurrence into met', function () {
    $occurrence = TaskOccurrence::factory()->forDefinition($this->task)->create([
        'period_key' => '2026-08-02',
        'period_start' => '2026-08-02',
        'period_end' => '2026-08-02',
        'status' => TaskOccurrenceStatus::Missed,
    ]);

    logEntry($this->task, ['value' => 2, 'occurred_at' => '2026-08-02 20:00'])->assertRedirect();

    expect($occurrence->fresh()->status)->toBe(TaskOccurrenceStatus::Met);
});

test('logging defaults to now when no time is given', function () {
    logEntry($this->task, ['value' => 0.5])->assertRedirect();

    expect(TaskEntry::sole()->occurred_at->toDateTimeString())->toBe('2026-08-05 12:00:00');
});

test('a non-stepped task replaces its previous entry', function () {
    $weight = TaskDefinition::factory()->forUser($this->user)->create([
        'active_from' => '2026-08-01',
        'measure' => 'weight',
        'unit' => 'kg',
        'comparator' => 'none',
        'aggregation' => 'last',
        'target_min' => null,
        'stepped' => false,
    ]);

    logEntry($weight, ['value' => 80, 'occurred_at' => '2026-08-05 07:00']);
    logEntry($weight, ['value' => 79.5, 'occurred_at' => '2026-08-05 08:00']);

    expect(TaskEntry::count())->toBe(1)
        ->and((float) TaskEntry::sole()->value)->toBe(79500.0);
});

test('logging on a day the schedule excludes is rejected', function () {
    $mondaysOnly = TaskDefinition::factory()->forUser($this->user)->create([
        'active_from' => '2026-08-01',
        'schedule' => TaskSchedule::from(['daysOfWeek' => [1]]),
    ]);

    // 2026-08-04 is a Tuesday.
    logEntry($mondaysOnly, ['value' => 1, 'occurred_at' => '2026-08-04 09:00'])
        ->assertSessionHasErrors(['occurred_at']);

    expect(TaskEntry::count())->toBe(0);
});

test('logging before the task starts is rejected', function () {
    logEntry($this->task, ['value' => 1, 'occurred_at' => '2026-07-30 09:00'])
        ->assertSessionHasErrors(['occurred_at']);
});

test('logging in the future is rejected', function () {
    logEntry($this->task, ['value' => 1, 'occurred_at' => '2030-01-01 09:00'])
        ->assertSessionHasErrors(['occurred_at']);
});

test('users cannot log against another users task', function () {
    $theirs = TaskDefinition::factory()->forUser(User::factory()->create())->create(['active_from' => '2026-08-01']);

    logEntry($theirs, ['value' => 1])->assertForbidden();
});

test('logging requires the log-entries permission', function () {
    $viewer = $this->createUserWithPermissions([TrackerPermission::ViewTasks]);
    $this->actingAs($viewer);

    $task = TaskDefinition::factory()->forUser($viewer)->create(['active_from' => '2026-08-01']);

    logEntry($task, ['value' => 1])->assertForbidden();
});

test('deleting an entry re-evaluates the occurrence', function () {
    logEntry($this->task, ['value' => 2, 'occurred_at' => '2026-08-05 09:00']);
    expect(occurrenceFor($this->task, '2026-08-05')->status)->toBe(TaskOccurrenceStatus::Met);

    $this->delete(route('admin.tracker.entries.destroy', TaskEntry::sole()))
        ->assertRedirect()
        ->assertSessionHas('success', 'Entry removed.');

    $occurrence = occurrenceFor($this->task, '2026-08-05');
    expect(TaskEntry::count())->toBe(0)
        ->and($occurrence->status)->toBe(TaskOccurrenceStatus::Pending)
        ->and($occurrence->completed_at)->toBeNull();
});

test('users cannot delete another users entry', function () {
    $theirTask = TaskDefinition::factory()->forUser(User::factory()->create())->create(['active_from' => '2026-08-01']);
    $occurrence = TaskOccurrence::factory()->forDefinition($theirTask)->create([
        'period_key' => '2026-08-05',
        'period_start' => '2026-08-05',
        'period_end' => '2026-08-05',
    ]);
    $entry = TaskEntry::factory()->forOccurrence($occurrence)->create();

    $this->delete(route('admin.tracker.entries.destroy', $entry))->assertForbidden();

    expect(TaskEntry::find($entry->id))->not->toBeNull();
});

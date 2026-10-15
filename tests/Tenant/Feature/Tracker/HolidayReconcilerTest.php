<?php

use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TaskOccurrenceStatus;
use App\Models\Tracker\Holiday;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use App\Services\Tracker\HolidayReconciler;
use Carbon\CarbonImmutable;

function reconciler(): HolidayReconciler
{
    return app(HolidayReconciler::class);
}

/**
 * Create a daily occurrence on $date for a definition.
 */
function occurrenceOn(TaskDefinition $definition, string $date, TaskOccurrenceStatus $status = TaskOccurrenceStatus::Pending): TaskOccurrence
{
    return TaskOccurrence::factory()->forDefinition($definition)->create([
        'period_key' => $date,
        'period_start' => $date,
        'period_end' => $date,
        'status' => $status,
        'is_holiday' => false,
    ]);
}

beforeEach(function () {
    // Reconciling re-evaluates occurrences, which depends on "today": pin it so
    // 2026-08-03 is the current (open) day.
    $this->travelTo(CarbonImmutable::parse('2026-08-03 12:00'));

    $this->user = User::factory()->create();
    $this->task = TaskDefinition::factory()->forUser($this->user)->create([
        'counts_during_holiday' => false,
        'status' => TaskDefinitionStatus::Active,
    ]);
});

test('it skips occurrences newly covered by a holiday', function () {
    $inside = occurrenceOn($this->task, '2026-08-03');
    $outside = occurrenceOn($this->task, '2026-08-10');

    Holiday::factory()->forUser($this->user)->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
    ]);

    $changed = reconciler()->reconcileForUser($this->user);

    expect($changed)->toBe(1)
        ->and($inside->fresh()->status)->toBe(TaskOccurrenceStatus::Skipped)
        ->and($inside->fresh()->is_holiday)->toBeTrue()
        ->and($outside->fresh()->status)->toBe(TaskOccurrenceStatus::Pending)
        ->and($outside->fresh()->is_holiday)->toBeFalse();
});

test('it reverts a previously skipped occurrence when no holiday covers it', function () {
    $occurrence = TaskOccurrence::factory()->forDefinition($this->task)->create([
        'period_key' => '2026-08-03',
        'period_start' => '2026-08-03',
        'period_end' => '2026-08-03',
        'status' => TaskOccurrenceStatus::Skipped,
        'is_holiday' => true,
    ]);

    // No holidays exist, so it should revert.
    $changed = reconciler()->reconcileForUser($this->user);

    expect($changed)->toBe(1)
        ->and($occurrence->fresh()->status)->toBe(TaskOccurrenceStatus::Pending)
        ->and($occurrence->fresh()->is_holiday)->toBeFalse();
});

test('a holiday never erases a met occurrence but still refreshes the holiday flag', function () {
    $met = occurrenceOn($this->task, '2026-08-03');
    // Meets the factory's 2000ml target.
    TaskEntry::factory()->forOccurrence($met)->create(['value' => 2000]);

    Holiday::factory()->forUser($this->user)->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
    ]);

    reconciler()->reconcileForUser($this->user);

    expect($met->fresh()->status)->toBe(TaskOccurrenceStatus::Met)
        ->and($met->fresh()->is_holiday)->toBeTrue();
});

test('it marks the holiday flag but keeps pending when the task counts during holidays', function () {
    $task = TaskDefinition::factory()->forUser($this->user)->create([
        'counts_during_holiday' => true,
    ]);
    $occurrence = occurrenceOn($task, '2026-08-03');

    Holiday::factory()->forUser($this->user)->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
    ]);

    reconciler()->reconcileForUser($this->user);

    expect($occurrence->fresh()->is_holiday)->toBeTrue()
        ->and($occurrence->fresh()->status)->toBe(TaskOccurrenceStatus::Pending);
});

test('creating a holiday via the controller reconciles existing occurrences', function () {
    $actingUser = $this->createUserWithPermissions([TrackerPermission::ManageHolidays]);
    $this->actingAs($actingUser);

    $task = TaskDefinition::factory()->forUser($actingUser)->create(['counts_during_holiday' => false]);
    $occurrence = occurrenceOn($task, '2026-08-03');

    $this->post(route('admin.tracker.holidays.store'), [
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
    ])->assertRedirect(route('admin.tracker.holidays.index'));

    expect($occurrence->fresh()->status)->toBe(TaskOccurrenceStatus::Skipped);
});

test('deleting a holiday via the controller reverts skipped occurrences', function () {
    $actingUser = $this->createUserWithPermissions([TrackerPermission::ManageHolidays]);
    $this->actingAs($actingUser);

    $task = TaskDefinition::factory()->forUser($actingUser)->create(['counts_during_holiday' => false]);
    $holiday = Holiday::factory()->forUser($actingUser)->create([
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
    ]);
    $occurrence = TaskOccurrence::factory()->forDefinition($task)->create([
        'period_key' => '2026-08-03',
        'period_start' => '2026-08-03',
        'period_end' => '2026-08-03',
        'status' => TaskOccurrenceStatus::Skipped,
        'is_holiday' => true,
    ]);

    $this->delete(route('admin.tracker.holidays.destroy', $holiday))->assertRedirect();

    expect($occurrence->fresh()->status)->toBe(TaskOccurrenceStatus::Pending)
        ->and($occurrence->fresh()->is_holiday)->toBeFalse();
});

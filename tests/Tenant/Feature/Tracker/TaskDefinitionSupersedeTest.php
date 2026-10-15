<?php

use App\Enums\Tracker\TaskDefinitionStatus;
use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;

/**
 * A valid update payload matching a factory-default task (daily min-2L water),
 * so that on its own it represents "no semantic change".
 *
 * @return array<string, mixed>
 */
function updatePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Drink water',
        'description' => 'Stay hydrated',
        'category_id' => null,
        'period' => 'daily',
        'measure' => 'volume',
        'unit' => 'l',
        'comparator' => 'min',
        'aggregation' => 'sum',
        // 2 litres — matches the factory default target of 2000ml base.
        'target_min' => 2,
        'target_max' => null,
        'stepped' => true,
        'counts_during_holiday' => false,
        'active_from' => '2026-08-01',
        'active_to' => null,
        'status' => 'active',
    ], $overrides);
}

/**
 * A factory task whose fields line up with updatePayload() (no schedule).
 */
function supersedableTask(User $user): TaskDefinition
{
    return TaskDefinition::factory()->forUser($user)->create([
        'title' => 'Drink water',
        'description' => 'Stay hydrated',
        'period' => 'daily',
        'measure' => 'volume',
        'unit' => 'l',
        'comparator' => 'min',
        'aggregation' => 'sum',
        'target_min' => 2000,
        'target_max' => null,
        'stepped' => true,
        'active_from' => '2026-08-01',
        'schedule' => null,
    ]);
}

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([TrackerPermission::ManageTasks]);
    $this->actingAs($this->user);
});

test('a cosmetic edit updates the task in place', function () {
    $task = supersedableTask($this->user);

    $this->put(route('admin.tracker.tasks.update', $task), updatePayload([
        'title' => 'Drink more water',
        'description' => 'Updated note',
        'unit' => 'ml',
        'target_min' => 2000, // 2000ml == the existing 2000ml base, no change
    ]))->assertRedirect(route('admin.tracker.tasks.index'));

    expect(TaskDefinition::count())->toBe(1);

    $task->refresh();
    expect($task->title)->toBe('Drink more water')
        ->and($task->status)->toBe(TaskDefinitionStatus::Active)
        ->and($task->active_to)->toBeNull();
});

test('changing the target supersedes the task', function () {
    $task = supersedableTask($this->user);

    $this->put(route('admin.tracker.tasks.update', $task), updatePayload([
        'target_min' => 3, // 3 litres — a new goal
    ]))->assertRedirect(route('admin.tracker.tasks.index'));

    expect(TaskDefinition::count())->toBe(2);

    // Old version archived and end-dated today.
    $task->refresh();
    expect($task->status)->toBe(TaskDefinitionStatus::Archived)
        ->and($task->active_to?->toDateString())->toBe(now()->toDateString());

    // New version is active, starts today, links back to the original lineage.
    $new = TaskDefinition::where('id', '!=', $task->id)->firstOrFail();
    expect($new->status)->toBe(TaskDefinitionStatus::Active)
        ->and($new->active_from?->toDateString())->toBe(now()->toDateString())
        ->and($new->lineage_id)->toBe($task->id)
        ->and((float) $new->target_min)->toBe(3000.0);
});

test('changing the comparator, period, aggregation or schedule supersedes', function (array $override) {
    $task = supersedableTask($this->user);

    $this->put(route('admin.tracker.tasks.update', $task), updatePayload($override));

    expect(TaskDefinition::count())->toBe(2);
})->with([
    'comparator' => [['comparator' => 'max', 'target_min' => null, 'target_max' => 5]],
    'period' => [['period' => 'weekly']],
    'aggregation' => [['aggregation' => 'last']],
    'schedule' => [['schedule' => ['daysOfWeek' => [1, 3, 5]]]],
]);

test('adding an empty schedule does not count as a semantic change', function () {
    $task = supersedableTask($this->user);

    // The form always sends a schedule object, even when nothing is selected.
    $this->put(route('admin.tracker.tasks.update', $task), updatePayload([
        'schedule' => ['daysOfWeek' => null, 'monthsOfYear' => null],
    ]));

    expect(TaskDefinition::count())->toBe(1);
});

test('successive semantic edits stay in the same lineage', function () {
    $task = supersedableTask($this->user);
    $originalId = $task->id;

    // First supersede: 2L -> 3L.
    $this->put(route('admin.tracker.tasks.update', $task), updatePayload(['target_min' => 3]));
    $v2 = TaskDefinition::where('status', TaskDefinitionStatus::Active->value)->firstOrFail();

    // Second supersede: 3L -> 4L.
    $this->put(route('admin.tracker.tasks.update', $v2), updatePayload(['target_min' => 4]));
    $v3 = TaskDefinition::where('status', TaskDefinitionStatus::Active->value)->firstOrFail();

    expect(TaskDefinition::count())->toBe(3)
        ->and($v2->fresh()->lineage_id)->toBe($originalId)
        ->and($v3->lineage_id)->toBe($originalId)
        // All three versions resolve to the same lineage key.
        ->and(TaskDefinition::inLineage($v3)->count())->toBe(3);
});

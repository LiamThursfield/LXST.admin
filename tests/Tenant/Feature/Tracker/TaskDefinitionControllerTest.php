<?php

use App\Models\Tracker\Category;
use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A valid store/update payload for a simple daily water goal.
 *
 * @return array<string, mixed>
 */
function validTaskPayload(array $overrides = []): array
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
        'target_min' => 2,
        'target_max' => null,
        'stepped' => true,
        'counts_during_holiday' => false,
        'active_from' => '2026-08-01',
        'active_to' => null,
        'status' => 'active',
    ], $overrides);
}

test('guests are redirected when accessing the task index', function () {
    $this->get(route('admin.tracker.tasks.index'))->assertRedirect(route('login'));
});

test('users without the view permission cannot see the task index', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->get(route('admin.tracker.tasks.index'))->assertForbidden();
});

test('users can see the task index with the view permission', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ViewTasks]));

    $this->get(route('admin.tracker.tasks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tracker/task/Index')
            ->has('tasks.data')
        );
});

test('the task index only shows the users own tasks', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ViewTasks]);
    $this->actingAs($user);

    TaskDefinition::factory()->forUser($user)->create(['title' => 'Mine']);
    TaskDefinition::factory()->forUser(User::factory()->create())->create(['title' => 'Theirs']);

    $this->get(route('admin.tracker.tasks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Mine')
        );
});

test('the task index can be filtered by title', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ViewTasks]);
    $this->actingAs($user);

    TaskDefinition::factory()->forUser($user)->create(['title' => 'Water']);
    TaskDefinition::factory()->forUser($user)->create(['title' => 'Workout']);

    $this->get(route('admin.tracker.tasks.index', ['filter[title]' => 'Wate']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Water')
        );
});

test('users can create a task and the target is stored in the base unit', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageTasks]);
    $this->actingAs($user);

    $response = $this->post(route('admin.tracker.tasks.store'), validTaskPayload());

    $response->assertRedirect(route('admin.tracker.tasks.index'));
    $response->assertSessionHas('success', 'Task created successfully.');

    $task = TaskDefinition::where('title', 'Drink water')->first();
    expect($task)->not->toBeNull()
        ->and($task->user_id)->toBe($user->id)
        // 2 litres stored as 2000 millilitres.
        ->and((float) $task->target_min)->toBe(2000.0);
});

test('users without the manage permission cannot create a task', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ViewTasks]));

    $this->post(route('admin.tracker.tasks.store'), validTaskPayload())->assertForbidden();
});

test('creating a task requires a min target for a min comparator', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageTasks]));

    $this->post(route('admin.tracker.tasks.store'), validTaskPayload(['target_min' => null]))
        ->assertSessionHasErrors(['target_min']);
});

test('creating a task rejects a unit that does not match the measure', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageTasks]));

    // Volume measure with a weight unit.
    $this->post(route('admin.tracker.tasks.store'), validTaskPayload(['unit' => 'kg']))
        ->assertSessionHasErrors(['unit']);
});

test('creating a task rejects a category the user cannot see', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageTasks]));

    $othersPrivate = Category::factory()->forUser(User::factory()->create())->create();

    $this->post(route('admin.tracker.tasks.store'), validTaskPayload(['category_id' => $othersPrivate->id]))
        ->assertSessionHasErrors(['category_id']);
});

test('a task can be created with a public category and a schedule', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageTasks]);
    $this->actingAs($user);

    $category = Category::factory()->global()->create();

    $response = $this->post(route('admin.tracker.tasks.store'), validTaskPayload([
        'category_id' => $category->id,
        'schedule' => ['daysOfWeek' => [1, 3, 5]],
    ]));

    $response->assertRedirect(route('admin.tracker.tasks.index'));

    $task = TaskDefinition::where('title', 'Drink water')->first();
    expect($task->category_id)->toBe($category->id)
        ->and($task->schedule->daysOfWeek)->toBe([1, 3, 5]);
});

test('users can update their own task', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageTasks]);
    $this->actingAs($user);

    $task = TaskDefinition::factory()->forUser($user)->create(['title' => 'Old']);

    $response = $this->put(
        route('admin.tracker.tasks.update', $task),
        validTaskPayload(['title' => 'New']),
    );

    $response->assertRedirect(route('admin.tracker.tasks.index'));
    expect($task->fresh()->title)->toBe('New');
});

test('users cannot update another users task', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageTasks]));

    $task = TaskDefinition::factory()->forUser(User::factory()->create())->create();

    $this->put(route('admin.tracker.tasks.update', $task), validTaskPayload())
        ->assertForbidden();
});

test('users can delete their own task', function () {
    $user = $this->createUserWithPermissions([TrackerPermission::ManageTasks]);
    $this->actingAs($user);

    $task = TaskDefinition::factory()->forUser($user)->create();

    $this->delete(route('admin.tracker.tasks.destroy', $task))->assertRedirect();
    expect(TaskDefinition::find($task->id))->toBeNull();
});

test('users cannot delete another users task', function () {
    $this->actingAs($this->createUserWithPermissions([TrackerPermission::ManageTasks]));

    $task = TaskDefinition::factory()->forUser(User::factory()->create())->create();

    $this->delete(route('admin.tracker.tasks.destroy', $task))->assertForbidden();
    expect(TaskDefinition::find($task->id))->not->toBeNull();
});

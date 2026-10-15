<?php

use App\Data\Tracker\TaskSchedule;
use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TrackerPeriod;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // 2026-08-05 is a Wednesday.
    $this->travelTo(CarbonImmutable::parse('2026-08-05 12:00'));

    $this->user = $this->createUserWithPermissions([
        TrackerPermission::ViewTasks,
        TrackerPermission::LogEntries,
    ]);
    $this->actingAs($this->user);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function todayTask(User $user, array $attributes = []): TaskDefinition
{
    return TaskDefinition::factory()->forUser($user)->create([
        'active_from' => '2026-08-01',
        'schedule' => null,
        ...$attributes,
    ]);
}

test('guests are redirected from the today page', function () {
    auth()->logout();

    $this->get(route('admin.tracker.today'))->assertRedirect(route('login'));
});

test('users without the view permission cannot see the today page', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->get(route('admin.tracker.today'))->assertForbidden();
});

test('it lists todays occurrence for each applicable task, creating it lazily', function () {
    todayTask($this->user, ['title' => 'Drink water']);

    $this->get(route('admin.tracker.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/tracker/today/Index')
            ->where('date', '2026-08-05')
            ->where('today', '2026-08-05')
            ->where('canLog', true)
            ->has('occurrences.data', 1)
            ->where('occurrences.data.0.definition.title', 'Drink water')
            ->where('occurrences.data.0.period_key', '2026-08-05')
            ->where('occurrences.data.0.status', 'pending')
        );

    expect(TaskOccurrence::count())->toBe(1);
});

test('it only shows the users own tasks', function () {
    todayTask($this->user, ['title' => 'Mine']);
    todayTask(User::factory()->create(), ['title' => 'Theirs']);

    $this->get(route('admin.tracker.today'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('occurrences.data', 1)
            ->where('occurrences.data.0.definition.title', 'Mine')
        );
});

test('it hides tasks the schedule excludes for the day', function () {
    todayTask($this->user, ['schedule' => TaskSchedule::from(['daysOfWeek' => [1]])]); // Mondays

    $this->get(route('admin.tracker.today'))
        ->assertInertia(fn (Assert $page) => $page->has('occurrences.data', 0));
});

test('it hides paused tasks', function () {
    todayTask($this->user, ['status' => TaskDefinitionStatus::Paused]);

    $this->get(route('admin.tracker.today'))
        ->assertInertia(fn (Assert $page) => $page->has('occurrences.data', 0));
});

test('it shows the current weeks occurrence for a weekly task', function () {
    todayTask($this->user, ['period' => TrackerPeriod::Weekly]);

    $this->get(route('admin.tracker.today'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('occurrences.data.0.period_key', '2026-W32')
        );
});

test('it returns values in the display unit', function () {
    // 2L goal stored as 2000ml.
    $task = todayTask($this->user, ['unit' => 'l', 'target_min' => 2000]);
    $occurrence = TaskOccurrence::factory()->forDefinition($task)->create([
        'period_key' => '2026-08-05',
        'period_start' => '2026-08-05',
        'period_end' => '2026-08-05',
    ]);
    TaskEntry::factory()->forOccurrence($occurrence)->create(['value' => 1500]);

    $this->get(route('admin.tracker.today'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('occurrences.data.0.definition.target_min', 2)
            ->where('occurrences.data.0.computed_value', 1.5)
            ->where('occurrences.data.0.entries.0.value', 1.5)
            ->where('occurrences.data.0.status', 'on-track')
        );
});

test('a past day can be viewed and is evaluated', function () {
    todayTask($this->user);

    $this->get(route('admin.tracker.today', ['date' => '2026-08-03']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('date', '2026-08-03')
            ->where('occurrences.data.0.period_key', '2026-08-03')
            // Nothing logged against a "min" goal on a finished day.
            ->where('occurrences.data.0.status', 'missed')
        );
});

test('a future day cannot be viewed', function () {
    $this->get(route('admin.tracker.today', ['date' => '2026-08-06']))
        ->assertSessionHasErrors(['date']);
});

test('a past day shows the version of a task that applied then', function () {
    // Superseded on 2026-08-04: 2L before, 3L from then on.
    $old = todayTask($this->user, [
        'target_min' => 2000,
        'status' => TaskDefinitionStatus::Archived,
        'active_to' => '2026-08-04',
    ]);
    todayTask($this->user, [
        'target_min' => 3000,
        'lineage_id' => $old->id,
        'active_from' => '2026-08-04',
    ]);

    $this->get(route('admin.tracker.today', ['date' => '2026-08-03']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('occurrences.data', 1)
            ->where('occurrences.data.0.definition.target_min', 2)
        );

    // On the switch-over day only the new version shows.
    $this->get(route('admin.tracker.today', ['date' => '2026-08-04']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('occurrences.data', 1)
            ->where('occurrences.data.0.definition.target_min', 3)
        );
});

test('users without the log permission get a read-only page', function () {
    $viewer = $this->createUserWithPermissions([TrackerPermission::ViewTasks]);
    $this->actingAs($viewer);

    $this->get(route('admin.tracker.today'))
        ->assertInertia(fn (Assert $page) => $page->where('canLog', false));
});
